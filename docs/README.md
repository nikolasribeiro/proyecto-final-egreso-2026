# Documentación — Proyecto ISBO 2026 (Hospital de Clínicas)

Documentación técnica y de entorno de la **Primera Entrega** del proyecto
ISBO 2026 — 3°ML, Bachillerato Tecnológico, UTU.

> **Stack del proyecto:** PHP 8.2 + Apache, MariaDB 10.11, Nginx (reverse
> proxy), phpMyAdmin, todo contenerizado con Docker / Podman. Sin
> frameworks de UI (CSS propio). Micro-framework MVC propio en PHP.
> Detalles completos en `Justificacion_Tecnologica.md`.

---

## Integrantes

- Juan de la Vega
- Leandro Conte
- Nicolas Pereyra
- Nicolas Ribeiro

---

## Contenido

| Documento | Descripción | Punto de la rúbrica |
|-----------|-------------|---------------------|
| [Justificación Tecnológica](./Justificacion_Tecnologica.md) | Fundamentos y argumentos del stack elegido (PHP, MariaDB, CSS propio, Docker, Git, VS Code). Conexión con necesidades del Hospital de Clínicas. | Punto 1 |
| [Modelado de Datos](./Modelado_Datos.md) | DER en Mermaid, Restricciones No Estructurales, Esquema Relacional y normalización hasta 3FN. Cubre ambos módulos (Documentación y Ambulancias). | Punto 4 |
| [Manual de Entorno de Desarrollo](./Manual_Entorno_Desarrollo.md) | Instalación paso a paso de Docker en Linux, configuración del proyecto, arquitectura del stack y checklist de verificación. | Punto 5 |

---

## Documentación complementaria

- **README raíz** [`../README.md`](../README.md) — descripción del
  proyecto, arquitectura de alto nivel, instrucciones rápidas para
  levantar el entorno, troubleshooting y **sección de Convenciones de
  Commits** (Punto 6 de la rúbrica).
- **Guía de Contribución** — disponible en la wiki del repositorio.
  Define el flujo de trabajo con ramas, prefijos de commit y pasos
  para contribuir al proyecto.

---

## Convenciones de nomenclatura de archivos

Los documentos siguen el patrón `Titulo_Con_Guiones_Bajos.md` (snake
case) para mantener consistencia en URLs de GitHub y enlaces
relativos.
