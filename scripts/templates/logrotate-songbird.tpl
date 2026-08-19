# /etc/logrotate.d/songbird
# Gestionado por songbird-operator (issue #35).

/var/log/songbird/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root adm
    sharedscripts
    postrotate
        # No hay servicio que necesite señal — solo stdout/stderr va al log
    endscript
}

/var/log/songbird-operator.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root adm
}
