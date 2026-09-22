-- Passkey-Anmeldungen loggen bisher als 'updated' im Änderungsprotokoll –
-- das lässt sie fälschlich auch als "Änderung" bei den neuen Admin-
-- Benachrichtigungen durchgehen (Login löst dann zusätzlich eine
-- Änderungs-Mail aus). Eigener ENUM-Wert dafür, analog zu den
-- impersonation_*-Werten aus v1.7.1.

ALTER TABLE audit_log
    MODIFY COLUMN action ENUM('created', 'updated', 'deleted', 'impersonation_started', 'impersonation_stopped', 'login') NOT NULL;
