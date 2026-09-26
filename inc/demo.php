<?php
/**
 * Demo data — a small, entirely fictional catalogue for trying the app.
 *
 * Every demo article code starts with DEMO- and every demo supplier with DEMO, so the
 * remover can find them without ever touching real data. Demo barcodes sit in the
 * 200–299 range, which GS1 reserves for in-store use, so they can never match a real product.
 */

function demo_catalogue() {
    return [
        'suppliers' => [
            // code, name, contact, email, lead days, order days
            ['DEMO-FRESH', 'Demo Fresh Foods',  'Orders desk',   'orders@fresh.example.com',  1, 'Mon, Wed, Fri'],
            ['DEMO-DRY',   'Demo Wholesale',    'Account team',  'sales@wholesale.example.com', 3, 'Tue'],
            ['DEMO-PACK',  'Demo Packaging',    'Customer care', 'hello@packaging.example.com', 5, 'Mon'],
        ],
        'categories' => [
            // name, kind
            ['Mains', 'product'], ['Sides', 'product'], ['Desserts', 'product'], ['Drinks', 'product'],
            ['Meat and protein', 'ingredient'], ['Bakery', 'ingredient'], ['Fresh produce', 'ingredient'],
            ['Dairy', 'ingredient'], ['Frozen', 'ingredient'], ['Sauces', 'ingredient'],
            ['Dry goods', 'ingredient'], ['Packaging', 'ingredient'],
        ],
        // code, name, category, unit, kind, cost, sell, supplier, pack size, pack cost, count days (0=Sun…6=Sat)
        'articles' => [
            ['DEMO-101', 'Classic Beef Burger',      'Mains',    'each',    'product',    1.85, 8.50, 'DEMO-FRESH', 1,   0,     []],
            ['DEMO-102', 'Crispy Chicken Wrap',      'Mains',    'each',    'product',    1.40, 7.50, 'DEMO-FRESH', 1,   0,     []],
            ['DEMO-103', 'Halloumi Grain Bowl',      'Mains',    'each',    'product',    1.70, 8.95, 'DEMO-FRESH', 1,   0,     []],
            ['DEMO-104', 'Loaded Fries',             'Sides',    'Portion', 'product',    0.65, 4.50, 'DEMO-DRY',   1,   0,     []],
            ['DEMO-105', 'Chocolate Brownie',        'Desserts', 'each',    'product',    0.45, 3.00, 'DEMO-DRY',   24,  10.80, [1,3,5]],
            ['DEMO-106', 'Salted Caramel Cookie',    'Desserts', 'each',    'product',    0.30, 2.20, 'DEMO-DRY',   30,  9.00,  [1,3,5]],
            ['DEMO-107', 'Sparkling Water 330ml',    'Drinks',   'Can',     'product',    0.35, 1.80, 'DEMO-DRY',   24,  8.40,  [1,3,5]],
            ['DEMO-108', 'Cola 330ml',               'Drinks',   'Can',     'product',    0.42, 1.90, 'DEMO-DRY',   24,  10.08, [1,3,5]],
            ['DEMO-201', 'Beef Patty 113g',          'Meat and protein', 'each', 'ingredient', 0.62, 0, 'DEMO-FRESH', 40, 24.80, [0,1,2,3,4,5,6]],
            ['DEMO-202', 'Chicken Thigh Fillet',     'Meat and protein', 'Kg',   'ingredient', 5.20, 0, 'DEMO-FRESH', 5,  26.00, [0,1,2,3,4,5,6]],
            ['DEMO-203', 'Halloumi Block 1kg',       'Dairy',    'each',    'ingredient', 8.40, 0, 'DEMO-FRESH', 6,   50.40, [0,1,2,3,4,5,6]],
            ['DEMO-204', 'Brioche Bun',              'Bakery',   'each',    'ingredient', 0.28, 0, 'DEMO-FRESH', 48,  13.44, [1,4]],
            ['DEMO-205', 'Flour Tortilla 12 inch',   'Bakery',   'each',    'ingredient', 0.14, 0, 'DEMO-DRY',   72,  10.08, [1,4]],
            ['DEMO-206', 'Iceberg Lettuce',          'Fresh produce', 'each', 'ingredient', 0.75, 0, 'DEMO-FRESH', 12, 9.00, [1,4]],
            ['DEMO-207', 'Red Onion',                'Fresh produce', 'Kg',   'ingredient', 0.95, 0, 'DEMO-FRESH', 10, 9.50, [1,4]],
            ['DEMO-208', 'Cheddar Slices',           'Dairy',    'Pack',    'ingredient', 3.60, 0, 'DEMO-FRESH', 4,   14.40, [1,4]],
            ['DEMO-209', 'Frozen Fries 2.5kg',       'Frozen',   'Bag',     'ingredient', 4.20, 0, 'DEMO-DRY',   4,   16.80, [1,4]],
            ['DEMO-210', 'Burger Sauce 2.2L',        'Sauces',   'Tub',     'ingredient', 6.50, 0, 'DEMO-DRY',   2,   13.00, [1]],
            ['DEMO-211', 'Garlic Aioli 1L',          'Sauces',   'Bottle',  'ingredient', 4.10, 0, 'DEMO-DRY',   6,   24.60, [1]],
            ['DEMO-212', 'Frying Oil 20L',           'Dry goods', 'each',   'ingredient', 28.00, 0, 'DEMO-DRY',  1,   28.00, [1]],
            ['DEMO-213', 'Burger Box, Kraft',        'Packaging', 'each',   'ingredient', 0.09, 0, 'DEMO-PACK',  500, 45.00, [1]],
            ['DEMO-214', 'Paper Carrier Bag, Large', 'Packaging', 'each',   'ingredient', 0.06, 0, 'DEMO-PACK',  250, 15.00, [1]],
            ['DEMO-215', 'Wooden Cutlery Set',       'Packaging', 'each',   'ingredient', 0.04, 0, 'DEMO-PACK',  500, 20.00, [1]],
        ],
        // barcode, article code, units per scan, label
        'barcodes' => [
            ['2000000000107', 'DEMO-107', 1,  ''],
            ['2000000000206', 'DEMO-107', 24, 'Case of 24'],
            ['2000000000305', 'DEMO-108', 1,  ''],
            ['2000000000404', 'DEMO-105', 1,  ''],
        ],
    ];
}

/** Units every install gets, demo or not. */
function default_units() {
    return ['each', 'Pack', 'Case', 'Box', 'Bag', 'Bottle', 'Can', 'Tub', 'Tray', 'Portion', 'Kg', 'g', 'Litre', 'ml'];
}

function demo_seed(PDO $pdo) {
    $d = demo_catalogue();
    $run = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
    $idOf = function ($table, $col, $val) use ($pdo) {
        $s = $pdo->prepare("SELECT id FROM $table WHERE $col=? LIMIT 1"); $s->execute([$val]); return $s->fetchColumn() ?: null;
    };

    foreach ($d['suppliers'] as $s) {
        if (!$idOf('suppliers', 'code', $s[0]))
            $run("INSERT INTO suppliers (code,name,contact,email,lead_days,order_days) VALUES (?,?,?,?,?,?)", $s);
    }
    $sort = 0;
    foreach ($d['categories'] as $c) {
        $sort += 10;
        if (!$idOf('categories', 'name', $c[0]))
            $run("INSERT INTO categories (name,kind,sort) VALUES (?,?,?)", [$c[0], $c[1], $sort]);
    }
    foreach ($d['articles'] as $a) {
        if ($idOf('articles', 'code', $a[0])) continue;
        $run("INSERT INTO articles (code,name,category_id,unit_id,kind,cost_price,sell_price,supplier_id,pack_size,pack_cost)
              VALUES (?,?,?,?,?,?,?,?,?,?)",
             [$a[0], $a[1], $idOf('categories', 'name', $a[2]), $idOf('units', 'name', $a[3]), $a[4], $a[5], $a[6],
              $idOf('suppliers', 'code', $a[7]), $a[8], $a[9]]);
        $aid = $idOf('articles', 'code', $a[0]);
        if ($a[10]) {
            $days = []; for ($i = 0; $i < 7; $i++) $days[] = in_array($i, $a[10], true) ? 1 : 0;
            $run("INSERT INTO schedules (article_id,shop_id,d0,d1,d2,d3,d4,d5,d6) VALUES (?,NULL,?,?,?,?,?,?,?)",
                 array_merge([$aid], $days));
        }
    }
    foreach ($d['barcodes'] as $b) {
        if ($idOf('barcodes', 'barcode', $b[0])) continue;
        $run("INSERT INTO barcodes (barcode,article_id,pack_qty,label,created_at) VALUES (?,?,?,?,NOW())",
             [$b[0], $idOf('articles', 'code', $b[1]), $b[2], $b[3]]);
    }
}


/**
 * Sample data shipped by 1.4.0 and earlier. It did not use DEMO- codes, so it is matched on
 * code AND name together — a real article that merely shares a code is never touched.
 */
function legacy_sample() {
    return [
      'articles' => [
        ['P1001', 'Sourdough Bloomer Loaf'],
        ['P1002', 'Malted Brown Loaf 12mm'],
        ['P1010', 'White & Wholemeal Roll'],
        ['P1012', 'Sandwich Baguette'],
        ['P2001', 'Sausage Roll'],
        ['P2002', 'Steak Bake'],
        ['P2003', 'Vegan Roll'],
        ['P3001', 'Glazed Ring Doughnut'],
        ['P3003', 'Caramel Shortbread'],
        ['P4001', 'Cheese Ploughmans Sandwich'],
        ['P5001', 'Mac and Cheese Hot Meal Box'],
        ['P6001', 'Still Water 500ml'],
        ['P6002', 'Orange Juice 330ml'],
        ['I9001', 'Caramel Syrup 1Ltr'],
        ['I9002', 'Cherry Syrup 1Ltr'],
        ['I9005', 'Sandwich Pickle'],
        ['I9010', 'White Sugar Sticks'],
        ['I9012', 'Cinnamon Sugar Dusting 400g'],
        ['I9020', 'Takeaway Cup 12oz']
      ],
      'suppliers'  => [['BAKE', 'Central Bakery'], ['AMB', 'Ambient Wholesale']],
      'categories' => ['Bread - Loaves', 'Bread - Rolls/Other', 'Savoury', 'Sweet', 'Sandwiches', 'Meal Solutions', 'Drinks', 'Syrups & Sauces', 'Dry Goods', 'Consumables'],
    ];
}

/** Article ids that belong to the demo data — today's and the old 1.4 sample. */
function demo_article_ids() {
    $ids = array_map('intval', array_column(all("SELECT id FROM articles WHERE code LIKE 'DEMO-%'"), 'id'));
    foreach (legacy_sample()['articles'] as [$code, $name]) {
        $id = col("SELECT id FROM articles WHERE code=? AND name=?", [$code, $name]);
        if ($id) $ids[] = (int)$id;
    }
    return array_values(array_unique($ids));
}

function demo_summary() {
    $ids = demo_article_ids();
    if (!$ids) return null;
    $in = implode(',', $ids);
    return [
        'articles'  => count($ids),
        'movements' => (int) col("SELECT COUNT(*) FROM movements WHERE article_id IN ($in)"),
        'orders'    => (int) col("SELECT COUNT(DISTINCT order_id) FROM order_lines WHERE article_id IN ($in)"),
    ];
}

/** Remove the demo data, and anything that only existed because of it. */
function demo_remove() {
    $ids = demo_article_ids();
    if (!$ids) return 0;
    $in = implode(',', $ids);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach (['movements', 'count_lines', 'waste_lines', 'order_lines', 'barcodes', 'schedules', 'par_levels'] as $t)
            $pdo->exec("DELETE FROM $t WHERE article_id IN ($in)");
        $pdo->exec("DELETE FROM articles WHERE id IN ($in)");
        // sheets and orders left with nothing on them
        $pdo->exec("DELETE FROM orders WHERE NOT EXISTS (SELECT 1 FROM order_lines l WHERE l.order_id=orders.id)");
        $pdo->exec("DELETE FROM count_sessions WHERE NOT EXISTS (SELECT 1 FROM count_lines l WHERE l.session_id=count_sessions.id)");
        $pdo->exec("DELETE FROM waste_sessions WHERE NOT EXISTS (SELECT 1 FROM waste_lines l WHERE l.session_id=waste_sessions.id)");
        // demo suppliers and categories nobody uses any more
        foreach (array_column(demo_catalogue()['suppliers'], 0) as $code)
            q("DELETE FROM suppliers WHERE code=? AND NOT EXISTS (SELECT 1 FROM articles a WHERE a.supplier_id=suppliers.id)", [$code]);
        foreach (legacy_sample()['suppliers'] as [$code, $name])
            q("DELETE FROM suppliers WHERE code=? AND name=? AND NOT EXISTS (SELECT 1 FROM articles a WHERE a.supplier_id=suppliers.id)
               AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.supplier_id=suppliers.id)", [$code, $name]);
        $cats = array_merge(array_column(demo_catalogue()['categories'], 0), legacy_sample()['categories']);
        foreach (array_unique($cats) as $name)
            q("DELETE FROM categories WHERE name=? AND NOT EXISTS (SELECT 1 FROM articles a WHERE a.category_id=categories.id)", [$name]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return count($ids);
}
