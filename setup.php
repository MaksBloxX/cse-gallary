<?php
/* Run once: php setup.php  — creates gallery.db with demo data */
$pdo = new PDO('sqlite:' . __DIR__ . '/gallery.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'Other',
  event_date TEXT NOT NULL,
  description TEXT,
  created_at TEXT
);
CREATE TABLE IF NOT EXISTS media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  file_path TEXT NOT NULL,
  file_type TEXT NOT NULL CHECK(file_type IN ('image','video')),
  sort_order INTEGER,
  uploaded_at TEXT
);
");

if (!$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn()) {
    $hash = password_hash('ebaub123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)")->execute([$hash]);

    $events = [
        ['AI & Machine Learning Seminar 2025', 'Seminar', '2025-03-12', 'A full-day seminar on Artificial Intelligence and its applications in agriculture, with guest speakers from industry.', 'demo_seminar.jpg'],
        ['Intra-University Programming Contest 2025', 'Contest', '2025-05-20', 'Annual IUPC where 40 teams competed to solve challenging algorithmic problems. Congratulations to the winners!', 'demo_contest.jpg'],
        ['Study Tour: Software Companies, Dhaka', 'Study Tour', '2024-11-08', 'Final-year students visited leading software companies in Dhaka to learn about real-world development practices.', 'demo_tour.jpg'],
        ['Freshers Reception — Spring 2025', 'Cultural', '2025-02-02', 'Warm welcome ceremony for the newest members of the CSE family, with cultural performances and prize giving.', 'demo_freshers.jpg'],
    ];
    $insE = $pdo->prepare("INSERT INTO events (title, category, event_date, description, created_at) VALUES (?,?,?,?,datetime('now'))");
    $insM = $pdo->prepare("INSERT INTO media (event_id, file_path, file_type, uploaded_at) VALUES (?,?,?,datetime('now'))");
    foreach ($events as [$t, $c, $d, $desc, $img]) {
        $insE->execute([$t, $c, $d, $desc]);
        $insM->execute([$pdo->lastInsertId(), 'uploads/events/' . $img, 'image']);
    }
    echo "Demo database created. Admin login -> username: admin, password: ebaub123\n";
} else {
    echo "Database already exists, skipping seed.\n";
}
