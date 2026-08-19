# /etc/ssh/sshd_config.d/00-songbird-hardening.conf
# Gestionado por songbird-operator (issue #35). No editar a mano.
# Para regenerar: sudo scripts/operator.sh --module ssh

# Puerto SSH (cambiado de 22 para reducir superficie de ataque)
Port {{SSH_PORT}}

# Autenticacion
PermitRootLogin no
PasswordAuthentication no
ChallengeResponseAuthentication no
PubkeyAuthentication yes
UsePAM yes

# Usuarios permitidos (allowlist explicita)
AllowUsers {{ADMIN_USER}}

# Forwarding y tunneling
X11Forwarding no
AllowAgentForwarding no
AllowTcpForwarding no
PermitTunnel no

# Timeouts y reintentos
ClientAliveInterval 300
ClientAliveCountMax 2
MaxAuthTries 3
LoginGraceTime 30
MaxSessions 4

# Logging
LogLevel VERBOSE

# Misc
Banner none
PrintMotd no
PrintLastLog yes
TCPKeepAlive no
Compression no
UseDNS no
