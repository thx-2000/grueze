-- Änderungsverlauf mit Altwerten: je Audit-Eintrag werden die geänderten
-- Felder als JSON (Feld → alt/neu) mitgeschrieben. Nur ab jetzt, Altbestand
-- lässt sich nicht rückwirkend füllen.
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS changes LONGTEXT NULL AFTER details;
