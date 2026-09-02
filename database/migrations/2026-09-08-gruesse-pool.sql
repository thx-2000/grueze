-- Grüße-Pool: editierbare Standard-Wünsche für Geburtstag und Weihnachten.
-- Beim Versand wird je Empfänger zufällig einer gezogen – so bekommt nicht
-- die ganze Stufe denselben Text. Platzhalter {Anrede}/{Vorname}/{Nachname}
-- werden wie bei Rundmails ersetzt.
CREATE TABLE IF NOT EXISTS greetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    occasion ENUM('birthday', 'christmas') NOT NULL,
    text TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_greetings_occasion (occasion, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO greetings (occasion, text, sort_order)
SELECT occasion, text, sort_order FROM (
    SELECT 'birthday' AS occasion, 'Alles Gute zum Geburtstag, {Vorname}! Ich denk an dich und hoffe, dein Tag wird richtig schön.' AS text, 1 AS sort_order UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, herzlichen Glückwunsch! Lass dich heute feiern und ordentlich verwöhnen.', 2 UNION ALL
    SELECT 'birthday', 'Happy Birthday, {Vorname}! Ich wünsch dir ein Jahr voller guter Momente und Menschen, die dir wichtig sind.', 3 UNION ALL
    SELECT 'birthday', 'Alles Liebe zum Geburtstag, {Vorname}. Schön, dass es dich gibt.', 4 UNION ALL
    SELECT 'birthday', '{Vorname}, ich wünsch dir von Herzen alles Gute – Gesundheit, Lachen und Zeit für die Dinge, die dir guttun.', 5 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Ich hoffe, du hast heute jemanden um dich, der dich hochleben lässt.', 6 UNION ALL
    SELECT 'birthday', 'Alles Gute, {Vorname}! Mögen dir im neuen Lebensjahr mehr Türen aufgehen als zufallen.', 7 UNION ALL
    SELECT 'birthday', '{Vorname}, feier schön! Du hast einen richtig guten Tag verdient.', 8 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag wünsch ich dir alles Gute, {Vorname} – und dass du dir heute selbst etwas Gutes tust.', 9 UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, ich denk an dich zum Geburtstag. Bleib, wie du bist.', 10 UNION ALL
    SELECT 'birthday', 'Alles Liebe, {Vorname}! Ich hoffe, das neue Jahr bringt dir viel, worüber du dich freuen kannst.', 11 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Auf ein Jahr mit weniger Stress und mehr von dem, was dir Freude macht.', 12 UNION ALL
    SELECT 'birthday', '{Vorname}, alles Gute zum Geburtstag! Ich freu mich, wenn wir uns bald mal wiedersehen.', 13 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag alles Gute, {Vorname}. Lass es dir heute richtig gutgehen.', 14 UNION ALL
    SELECT 'birthday', 'Happy Birthday, {Vorname}! Ich wünsch dir ein Lächeln im Gesicht und nette Menschen an deiner Seite.', 15 UNION ALL
    SELECT 'birthday', '{Vorname}, herzlichen Glückwunsch! Nimm dir heute Zeit für dich – du hast sie dir verdient.', 16 UNION ALL
    SELECT 'birthday', 'Alles Gute zum Ehrentag, {Vorname}! Ich hoff, du wirst heute ordentlich gefeiert.', 17 UNION ALL
    SELECT 'birthday', '{Vorname}, ich schick dir die herzlichsten Geburtstagsgrüße. Mach was Schönes draus.', 18 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag wünsch ich dir Gesundheit, gute Laune und ein paar richtig schöne Überraschungen, {Vorname}.', 19 UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, alles Liebe zum Geburtstag! Schön, dich zu kennen.', 20 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Ich hoffe, das kommende Jahr ist gut zu dir.', 21 UNION ALL
    SELECT 'birthday', 'Alles Gute, {Vorname} – und danke, dass du so bist, wie du bist. Feier schön!', 22 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Ich wünsch dir ruhige Tage und einen guten Start ins neue Jahr.', 1 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, schöne Feiertage! Ich hoffe, du kommst zur Ruhe und hast nette Menschen um dich.', 2 UNION ALL
    SELECT 'christmas', '{Vorname}, ich denk an dich zum Fest. Hab eine warme, entspannte Weihnachtszeit.', 3 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Lass es dir gutgehen und genieß die freien Tage.', 4 UNION ALL
    SELECT 'christmas', 'Dir und deinen Liebsten frohe Weihnachten, {Vorname}. Kommt gut und gesund ins neue Jahr.', 5 UNION ALL
    SELECT 'christmas', '{Vorname}, schöne Feiertage! Ich hoffe, zwischen allem Trubel bleibt Zeit für dich.', 6 UNION ALL
    SELECT 'christmas', 'Frohes Fest, {Vorname}! Ich wünsch dir gemütliche Stunden und ein bisschen Pause vom Alltag.', 7 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, ich schick dir herzliche Weihnachtsgrüße. Hab eine schöne Zeit.', 8 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Auf ein neues Jahr mit guten Begegnungen – vielleicht auch mal wieder mit dir.', 9 UNION ALL
    SELECT 'christmas', '{Vorname}, ich wünsch dir besinnliche Feiertage und einen entspannten Rutsch ins neue Jahr.', 10 UNION ALL
    SELECT 'christmas', 'Schöne Weihnachten, {Vorname}! Ich hoffe, das Jahr klingt für dich versöhnlich aus.', 11 UNION ALL
    SELECT 'christmas', 'Frohes Fest und alles Gute fürs neue Jahr, {Vorname}. Pass auf dich auf.', 12 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, frohe Weihnachten! Mach es dir gemütlich und lass den Stress mal liegen.', 13 UNION ALL
    SELECT 'christmas', '{Vorname}, ich denk an dich zu den Feiertagen. Hab es schön und komm gut ins neue Jahr.', 14 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Ich wünsch dir warmes Licht, gutes Essen und Zeit mit den richtigen Leuten.', 15 UNION ALL
    SELECT 'christmas', 'Dir eine friedliche Weihnachtszeit, {Vorname}, und ein neues Jahr, das gut zu dir ist.', 16 UNION ALL
    SELECT 'christmas', '{Vorname}, schöne Feiertage! Danke, dass du das Jahr über dabei warst.', 17 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname} – und für das neue Jahr wünsch ich dir Gesundheit und viel Grund zur Freude.', 18
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM greetings);
