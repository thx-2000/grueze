-- „Kreide" wird das neue Standard-Theme. Das aktive Theme einmalig umstellen –
-- jederzeit unter Verwaltung → Themes wieder änderbar (auch auf ein eigenes).
INSERT INTO app_settings (setting_key, setting_value) VALUES ('active_theme', 'kreide')
ON DUPLICATE KEY UPDATE setting_value = 'kreide';
