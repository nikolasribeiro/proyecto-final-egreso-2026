/**
 * Sincroniza datos del issue padre al PR:
 *  - Assignees, labels, milestone y "Closes #N" en el body (REST).
 *  - Project v2: agrega el PR al mismo project del issue y copia
 *    los valores de los campos Sprint, Priority y Status (GraphQL).
 *
 * Ejecutado por `actions/github-script@v7` desde pr-sync.yml.
 *
 * Recibe { github, context, core } y expone una función main() async.
 */

// Campos a sincronizar del issue padre al PR (case-sensitive).
// El kind define qué mutation GraphQL se usa para setear el valor.
const FIELD_TARGETS = [
  { name: "Sprint",  kind: "iteration",     fieldType: "ProjectV2IterationField" },
  { name: "Priority", kind: "singleSelect",  fieldType: "ProjectV2SingleSelectField" },
  { name: "Status",   kind: "singleSelect",  fieldType: "ProjectV2SingleSelectField" }
];

/**
 * Trunca un nodeId para logging seguro.
 */
function truncateId(id) {
  if (!id) return "(null)";
  return id.length > 12 ? `${id.substring(0, 12)}...` : id;
}

/**
 * Wrapper sobre github.graphql() con reintentos para errores transitorios.
 */
async function graphqlWithRetry(github, query, variables, maxRetries = 3) {
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      return await github.graphql(query, variables);
    } catch (err) {
      const msg = (err && err.message) || String(err);
      const transient = /rate limit|timeout|timed out|secondary rate limit/i.test(msg);
      if (!transient || attempt === maxRetries) throw err;
      const delay = Math.pow(2, attempt) * 500;
      console.log(`[WARN] GraphQL transitorio (intento ${attempt}/${maxRetries}), reintentando en ${delay}ms: ${msg}`);
      await new Promise((r) => setTimeout(r, delay));
    }
  }
}

/**
 * Devuelve el nodeId de un issue (o PR) por número.
 */
async function getNodeId(github, owner, name, number) {
  const data = await graphqlWithRetry(github, `
    query($owner: String!, $name: String!, $n: Int!) {
      repository(owner: $owner, name: $name) {
        issue(number: $n) { id }
      }
    }`, { owner, name, n: number });
  if (!data || !data.repository || !data.repository.issue) {
    throw new Error(`No se pudo resolver el nodeId del issue #${number}`);
  }
  return data.repository.issue.id;
}

/**
 * Lista los items de Project v2 a los que pertenece el issue, junto con
 * los valores de los campos relevantes (Sprint/Priority/Status).
 *
 * Devuelve [] si el issue no pertenece a ningún project.
 */
async function getIssueProjectsWithFields(github, issueNodeId) {
  const data = await graphqlWithRetry(github, `
    query($id: ID!) {
      node(id: $id) {
        ... on Issue {
          projectItems(first: 20) {
            nodes {
              id
              project { id title number }
              fieldValues(first: 30) {
                nodes {
                  __typename
                  ... on ProjectV2ItemFieldSingleSelectValue {
                    name
                    optionId
                    field { ... on ProjectV2SingleSelectField { id name } }
                  }
                  ... on ProjectV2ItemFieldIterationValue {
                    title
                    iterationId
                    field { ... on ProjectV2IterationField { id name } }
                  }
                  ... on ProjectV2ItemFieldTextValue {
                    text
                    field { ... on ProjectV2FieldCommon { id name } }
                  }
                  ... on ProjectV2ItemFieldNumberValue {
                    number
                    field { ... on ProjectV2FieldCommon { id name } }
                  }
                  ... on ProjectV2ItemFieldDateValue {
                    date
                    field { ... on ProjectV2FieldCommon { id name } }
                  }
                }
              }
            }
          }
        }
      }
    }`, { id: issueNodeId });

  if (!data || !data.node || !data.node.projectItems) return [];
  return data.node.projectItems.nodes || [];
}

/**
 * Agrega el PR al project v2. Si ya está, busca el itemId existente.
 */
async function findOrAddProjectItem(github, projectId, prNodeId, core) {
  try {
    const added = await graphqlWithRetry(github, `
      mutation($pid: ID!, $cid: ID!) {
        addProjectV2ItemById(input: { projectId: $pid, contentId: $cid }) {
          item { id }
        }
      }`, { pid: projectId, cid: prNodeId });
    const newItemId = added.addProjectV2ItemById.item.id;
    console.log(`[INFO] PR agregado al project como item ${truncateId(newItemId)}`);
    return newItemId;
  } catch (err) {
    const msg = (err && err.message) || String(err);
    if (!/already exists|already in project|item already/i.test(msg)) {
      throw err;
    }
    console.log(`[INFO] PR ya estaba en el project, buscando item existente`);
  }

  // Fallback: buscar el item existente dentro del project.
  const data = await graphqlWithRetry(github, `
    query($pid: ID!) {
      node(id: $pid) {
        ... on ProjectV2 {
          items(first: 100) {
            nodes {
              id
              content {
                ... on PullRequest { id number }
                ... on Issue { id number }
              }
            }
          }
        }
      }
    }`, { pid: projectId });

  const nodes = (data && data.node && data.node.items && data.node.items.nodes) || [];
  const existing = nodes.find((n) => n.content && n.content.id === prNodeId);
  if (!existing) {
    throw new Error(`No se pudo encontrar el item existente del PR en el project`);
  }
  console.log(`[INFO] Item existente del PR: ${truncateId(existing.id)}`);
  return existing.id;
}

/**
 * Copia los valores de FIELD_TARGETS desde el item del issue padre
 * al item del PR. Los campos faltantes o sin valor se omiten.
 */
async function copyFieldsToPR(github, sourceItem, targetItemId, projectId, core) {
  // Diagnóstico: listar todos los field values encontrados en el source.
  const allFields = (sourceItem.fieldValues.nodes || []).map((fv) => ({
    name: fv.field && fv.field.name,
    type: fv.field && fv.field.__typename,
    valueType: fv.__typename
  }));
  console.log(`[DEBUG] Field values en source (${allFields.length}): ${JSON.stringify(allFields)}`);

  for (const target of FIELD_TARGETS) {
    const sourceFieldValue = (sourceItem.fieldValues.nodes || []).find(
      (fv) => fv.field && fv.field.name === target.name && fv.field.__typename === target.fieldType
    );

    if (!sourceFieldValue) {
      console.log(`[INFO] Campo "${target.name}" no encontrado en source o tipo incorrecto, saltando`);
      continue;
    }

    if (target.kind === "singleSelect") {
      if (!sourceFieldValue.optionId) {
        console.log(`[INFO] "${target.name}" sin valor asignado en source, saltando`);
        continue;
      }
      try {
        await graphqlWithRetry(github, `
          mutation($pid: ID!, $iid: ID!, $fid: ID!, $oid: String) {
            updateProjectV2ItemFieldValue(input: {
              projectId: $pid
              itemId: $iid
              fieldId: $fid
              value: { singleSelectOptionId: $oid }
            }) { projectV2Item { id } }
          }`, {
            pid: projectId,
            iid: targetItemId,
            fid: sourceFieldValue.field.id,
            oid: sourceFieldValue.optionId
          });
        console.log(`[INFO] "${target.name}" = "${sourceFieldValue.name}" copiado`);
      } catch (err) {
        console.log(`[WARN] No se pudo copiar "${target.name}": ${(err && err.message) || err}`);
      }
      continue;
    }

    if (target.kind === "iteration") {
      if (!sourceFieldValue.iterationId) {
        console.log(`[INFO] "${target.name}" sin valor asignado en source, saltando`);
        continue;
      }
      try {
        await graphqlWithRetry(github, `
          mutation($pid: ID!, $iid: ID!, $fid: ID!, $iid2: ID) {
            updateProjectV2ItemFieldValue(input: {
              projectId: $pid
              itemId: $iid
              fieldId: $fid
              value: { iterationId: $iid2 }
            }) { projectV2Item { id } }
          }`, {
            pid: projectId,
            iid: targetItemId,
            fid: sourceFieldValue.field.id,
            iid2: sourceFieldValue.iterationId
          });
        console.log(`[INFO] "${target.name}" = "${sourceFieldValue.title}" copiado`);
      } catch (err) {
        console.log(`[WARN] No se pudo copiar "${target.name}": ${(err && err.message) || err}`);
      }
    }
  }
}

/**
 * Sincroniza el PR con todos los Project v2 del issue padre.
 * Si el issue no está en ningún project, termina sin error.
 */
async function syncProjects(github, context, core) {
  console.log(`\n=== Sincronización de Project v2 ===`);

  const title = (context.payload.pull_request && context.payload.pull_request.title) || "";
  const match = title.match(/#(\d+)/);
  if (!match) {
    console.log(`[INFO] No se encontró issue number en el título, saltando Project v2 sync`);
    return;
  }
  const parentIssueNum = parseInt(match[1], 10);

  const prNodeId = context.payload.pull_request.node_id;
  if (!prNodeId) {
    console.log(`[WARN] El PR no tiene node_id en el payload, saltando Project v2 sync`);
    return;
  }
  console.log(`[INFO] PR nodeId: ${truncateId(prNodeId)}`);

  let parentIssueNodeId;
  try {
    parentIssueNodeId = await getNodeId(github, context.repo.owner, context.repo.repo, parentIssueNum);
  } catch (err) {
    console.log(`[WARN] No se pudo resolver el issue padre #${parentIssueNum}: ${(err && err.message) || err}`);
    return;
  }
  console.log(`[INFO] Issue padre #${parentIssueNum} nodeId: ${truncateId(parentIssueNodeId)}`);

  let parentProjects;
  try {
    parentProjects = await getIssueProjectsWithFields(github, parentIssueNodeId);
  } catch (err) {
    console.log(`[WARN] No se pudieron listar los projects del issue: ${(err && err.message) || err}`);
    return;
  }

  if (parentProjects.length === 0) {
    console.log(`[INFO] Issue padre no está en ningún Project v2, no se sincroniza nada`);
    return;
  }

  const titles = parentProjects.map((p) => p.project && p.project.title).filter(Boolean).join(", ");
  console.log(`[INFO] Issue padre está en ${parentProjects.length} project(s): ${titles}`);

  for (const sourceItem of parentProjects) {
    const project = sourceItem.project || {};
    console.log(`\n=== Project: ${project.title} (#${project.number}) ===`);

    let targetItemId;
    try {
      targetItemId = await findOrAddProjectItem(github, project.id, prNodeId, core);
    } catch (err) {
      console.log(`[WARN] No se pudo agregar/buscar el item del PR en "${project.title}": ${(err && err.message) || err}`);
      continue;
    }

    try {
      await copyFieldsToPR(github, sourceItem, targetItemId, project.id, core);
    } catch (err) {
      console.log(`[WARN] Error copiando campos en "${project.title}": ${(err && err.message) || err}`);
    }
  }
}

/**
 * Entry point: sincroniza assignees, labels, milestone, body y Project v2.
 * Los errores recuperables se loguean; los irrecuperables lanzan excepción.
 */
async function main({ github, context, core }) {
  console.log(`\n=== Inicio de sincronización de PR #${context.issue.number} ===`);

  // 1. Asignar el autor del PR al propio PR.
  await github.rest.issues.addAssignees({
    issue_number: context.issue.number,
    owner: context.repo.owner,
    repo: context.repo.repo,
    assignees: [context.payload.pull_request.user.login]
  });

  // 2. Extraer issue number del título.
  const title = context.payload.pull_request.title || "";
  const match = title.match(/#(\d+)/);
  if (!match) {
    console.log(`[INFO] El título del PR no contiene un #N, saltando sincronización con issue`);
    return;
  }
  const issueNum = parseInt(match[1], 10);
  console.log(`[INFO] Issue padre detectado: #${issueNum}`);

  // 3. Obtener el issue padre.
  let issue;
  try {
    const { data } = await github.rest.issues.get({
      owner: context.repo.owner,
      repo: context.repo.repo,
      issue_number: issueNum
    });
    issue = data;
  } catch (err) {
    if (err && err.status === 404) {
      console.log(`[WARN] Issue #${issueNum} no existe, saltando sync`);
      return;
    }
    throw err;
  }

  // 4. Sincronizar labels.
  const labels = (issue.labels || []).map((l) => l.name);
  if (labels.length > 0) {
    await github.rest.issues.addLabels({
      issue_number: context.issue.number,
      owner: context.repo.owner,
      repo: context.repo.repo,
      labels
    });
    console.log(`[INFO] Labels sincronizados: ${labels.join(", ")}`);
  }

  // 5. Sincronizar milestone.
  if (issue.milestone) {
    await github.rest.issues.update({
      issue_number: context.issue.number,
      owner: context.repo.owner,
      repo: context.repo.repo,
      milestone: issue.milestone.number
    });
    console.log(`[INFO] Milestone sincronizado: ${issue.milestone.title}`);
  }

  // 6. Agregar "Closes #N" al body si falta.
  const currentBody = context.payload.pull_request.body || "";
  const linkText = `Closes #${issueNum}`;
  if (!currentBody.includes(linkText)) {
    await github.rest.pulls.update({
      pull_number: context.issue.number,
      owner: context.repo.owner,
      repo: context.repo.repo,
      body: currentBody + `\n\n---\n*Auto-vinculación:* ${linkText}`
    });
    console.log(`[INFO] Body actualizado con "${linkText}"`);
  }

  // 7. Sincronizar Project v2 (Sprint, Priority, Status).
  try {
    await syncProjects(github, context, core);
  } catch (err) {
    console.log(`[WARN] Sync de Project v2 falló: ${(err && err.message) || err}`);
  }
}

module.exports = { main };
