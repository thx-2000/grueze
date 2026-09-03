-- „Als diese Person anmelden" wird jetzt auch aus dem Kontakt heraus angeboten.
-- Start und Ende der Sitzung gehören ins Änderungsprotokoll – dafür braucht das
-- ENUM zwei zusätzliche Werte (bisher scheiterte der Audit-Eintrag im
-- Strict-Mode still bzw. mit Fehler).
ALTER TABLE audit_log
    MODIFY COLUMN action ENUM('created', 'updated', 'deleted', 'impersonation_started', 'impersonation_stopped') NOT NULL;
