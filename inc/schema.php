<?php
/** Every table the app uses. Safe to run again — it only creates what is missing. */

function mustr_tables() {
    return [
'shops' => "CREATE TABLE IF NOT EXISTS shops (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL, name VARCHAR(120) NOT NULL,
  address VARCHAR(190) DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'users' => "CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL, role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
  shop_id INT DEFAULT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME DEFAULT NULL,
  UNIQUE KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'categories' => "CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
  kind ENUM('product','ingredient') NOT NULL DEFAULT 'product', sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'units' => "CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(40) NOT NULL, UNIQUE KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'suppliers' => "CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL DEFAULT '', name VARCHAR(160) NOT NULL,
  contact VARCHAR(120) DEFAULT '', email VARCHAR(190) DEFAULT '', phone VARCHAR(60) DEFAULT '',
  address VARCHAR(255) DEFAULT '', lead_days INT NOT NULL DEFAULT 2,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0, order_days VARCHAR(20) DEFAULT '',
  notes VARCHAR(255) DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'articles' => "CREATE TABLE IF NOT EXISTS articles (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL, name VARCHAR(190) NOT NULL,
  category_id INT DEFAULT NULL, unit_id INT DEFAULT NULL,
  kind ENUM('product','ingredient') NOT NULL DEFAULT 'product',
  cost_price DECIMAL(10,4) NOT NULL DEFAULT 0, sell_price DECIMAL(10,4) NOT NULL DEFAULT 0,
  supplier_id INT DEFAULT NULL, supplier_ref VARCHAR(60) DEFAULT '',
  pack_size DECIMAL(10,3) NOT NULL DEFAULT 1, pack_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
  charity_ok TINYINT(1) NOT NULL DEFAULT 1, blocked TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1, notes VARCHAR(255) DEFAULT '',
  UNIQUE KEY (code), KEY (category_id), KEY (kind), KEY (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'par_levels' => "CREATE TABLE IF NOT EXISTS par_levels (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, article_id INT NOT NULL,
  par_qty DECIMAL(12,3) NOT NULL DEFAULT 0, pack_size DECIMAL(10,3) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_par (shop_id, article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'orders' => "CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, supplier_id INT NOT NULL,
  order_no VARCHAR(40) NOT NULL, order_date DATE NOT NULL, delivery_date DATE DEFAULT NULL,
  status ENUM('draft','sent','received','cancelled') NOT NULL DEFAULT 'draft',
  notes VARCHAR(255) DEFAULT '', created_by INT DEFAULT NULL, created_at DATETIME DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL, received_at DATETIME DEFAULT NULL,
  UNIQUE KEY (order_no), KEY (shop_id), KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'order_lines' => "CREATE TABLE IF NOT EXISTS order_lines (
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, article_id INT NOT NULL,
  suggested_qty DECIMAL(12,3) NOT NULL DEFAULT 0, qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  received_qty DECIMAL(12,3) DEFAULT NULL, unit_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
  pack_size DECIMAL(10,3) NOT NULL DEFAULT 1, note VARCHAR(120) DEFAULT '',
  UNIQUE KEY uniq_oline (order_id, article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'schedules' => "CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY, article_id INT NOT NULL, shop_id INT DEFAULT NULL,
  d0 TINYINT(1) NOT NULL DEFAULT 0, d1 TINYINT(1) NOT NULL DEFAULT 0, d2 TINYINT(1) NOT NULL DEFAULT 0,
  d3 TINYINT(1) NOT NULL DEFAULT 0, d4 TINYINT(1) NOT NULL DEFAULT 0, d5 TINYINT(1) NOT NULL DEFAULT 0,
  d6 TINYINT(1) NOT NULL DEFAULT 0, UNIQUE KEY uniq_sched (article_id, shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'count_sessions' => "CREATE TABLE IF NOT EXISTS count_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, count_date DATE NOT NULL,
  status ENUM('open','confirmed') NOT NULL DEFAULT 'open', created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_count (shop_id, count_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'count_lines' => "CREATE TABLE IF NOT EXISTS count_lines (
  id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, article_id INT NOT NULL,
  qty DECIMAL(12,3) DEFAULT NULL, expected_qty DECIMAL(12,3) DEFAULT NULL,
  variance_qty DECIMAL(12,3) DEFAULT NULL, variance_value DECIMAL(12,2) DEFAULT NULL,
  auto_added TINYINT(1) NOT NULL DEFAULT 0, UNIQUE KEY uniq_line (session_id, article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'waste_sessions' => "CREATE TABLE IF NOT EXISTS waste_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, waste_date DATE NOT NULL,
  waste_type ENUM('ingredient','stales','quality') NOT NULL,
  status ENUM('open','confirmed') NOT NULL DEFAULT 'open', created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_waste (shop_id, waste_date, waste_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'waste_lines' => "CREATE TABLE IF NOT EXISTS waste_lines (
  id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, article_id INT NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0, charity TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(120) DEFAULT '', line_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_wline (session_id, article_id, reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'movements' => "CREATE TABLE IF NOT EXISTS movements (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, article_id INT NOT NULL,
  mv_date DATE NOT NULL, mv_type VARCHAR(24) NOT NULL, qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  mv_value DECIMAL(12,2) NOT NULL DEFAULT 0, ref VARCHAR(80) DEFAULT '', created_at DATETIME DEFAULT NULL,
  KEY (shop_id, article_id), KEY (mv_date), KEY (mv_type), KEY (ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'barcodes' => "CREATE TABLE IF NOT EXISTS barcodes (
  id INT AUTO_INCREMENT PRIMARY KEY, barcode VARCHAR(64) NOT NULL, article_id INT NOT NULL,
  pack_qty DECIMAL(10,3) NOT NULL DEFAULT 1, label VARCHAR(60) DEFAULT '',
  created_by INT DEFAULT NULL, created_at DATETIME DEFAULT NULL,
  UNIQUE KEY (barcode), KEY (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'meta' => "CREATE TABLE IF NOT EXISTS meta (
  k VARCHAR(60) PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL, KEY (email), KEY (ip), KEY (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'sales_days' => "CREATE TABLE IF NOT EXISTS sales_days (
  id INT AUTO_INCREMENT PRIMARY KEY, shop_id INT NOT NULL, sales_date DATE NOT NULL,
  net_sales DECIMAL(12,2) NOT NULL DEFAULT 0, UNIQUE KEY uniq_sales (shop_id, sales_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

/** Add a column to an existing table if an older install does not have it yet. */
function mustr_ensure_column(PDO $pdo, $table, $column, $ddl) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $s->execute([$table, $column]);
    if (!$s->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
}

/** Create anything missing and bring an older install up to date. */
function mustr_migrate(PDO $pdo) {
    foreach (mustr_tables() as $sql) $pdo->exec($sql);
    mustr_ensure_column($pdo, 'articles', 'supplier_id',  'supplier_id INT DEFAULT NULL');
    mustr_ensure_column($pdo, 'articles', 'supplier_ref', "supplier_ref VARCHAR(60) DEFAULT ''");
    mustr_ensure_column($pdo, 'articles', 'pack_size',    'pack_size DECIMAL(10,3) NOT NULL DEFAULT 1');
    mustr_ensure_column($pdo, 'articles', 'pack_cost',    'pack_cost DECIMAL(10,4) NOT NULL DEFAULT 0');
    mustr_ensure_column($pdo, 'sales_days', 'net_sales', 'net_sales DECIMAL(12,2) NOT NULL DEFAULT 0');
    if (defined('MUSTR_VERSION')) {
        $s = $pdo->prepare("INSERT INTO meta (k,v) VALUES ('db_version',?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
        $s->execute([MUSTR_VERSION]);
    }
}
