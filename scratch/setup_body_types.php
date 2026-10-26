<?php
require_once __DIR__ . '/../config.php';

echo "=== Adding body_type column to product_models & products ===\n";

// 1. Alter product_models
$cols = $pdo->query("SHOW COLUMNS FROM product_models LIKE 'body_type'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE product_models ADD COLUMN body_type VARCHAR(50) DEFAULT NULL AFTER name, ADD INDEX idx_pm_body_type (body_type)");
    echo "Added 'body_type' column to product_models.\n";
} else {
    echo "'body_type' column already exists in product_models.\n";
}

// 2. Alter products table
$p_cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'body_type'")->fetchAll();
if (empty($p_cols)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN body_type VARCHAR(50) DEFAULT NULL AFTER variant, ADD INDEX idx_p_body_type (body_type)");
    echo "Added 'body_type' column to products.\n";
} else {
    echo "'body_type' column already exists in products.\n";
}

// 3. Populate Cars (Category 25)
$car_types = [
    // Hatchbacks
    'Hatchback' => [
        'Swift', 'Baleno', 'Alto 800', 'Alto K10', 'Celerio', 'Ignis', 'WagonR', 'Ritz', 'Zen', 'Maruti 800', 'S-Presso',
        'Grand i10 Nios', 'i20', 'i10', 'Santro', 'Eon',
        'Tiago', 'Altroz', 'Indica',
        'Jazz',
        'Glanza',
        'Polo',
        'Micra',
        'Kwid',
        'Comet EV'
    ],
    // Sedans
    'Sedan' => [
        'Dzire', 'Ciaz',
        'Aura', 'Verna', 'Accent',
        'Tigor', 'Indigo',
        'City', 'Amaze', 'Civic',
        'Etios',
        'Virtus', 'Vento',
        'Rapid', 'Slavia', 'Octavia',
        '2 Series Gran Coupe', '3 Series Gran Limousine', '5 Series 520d/530i', '7 Series 740i',
        'A-Class Limousine', 'C-Class C200/C220d', 'E-Class E200/E220d', 'S-Class S350d/S450',
        'A4 40 TFSI', 'A6 45 TFSI'
    ],
    // Compact SUV / Crossover
    'Compact SUV' => [
        'Fronx', 'Brezza', 'Jimny',
        'Exter', 'Venue',
        'Punch', 'Nexon', 'Curvv',
        'XUV 3XO', 'XUV300',
        'Urban Cruiser Hyryder',
        'Elevate', 'WR-V',
        'Sonet',
        'Taigun',
        'Kushaq',
        'Kiger', 'Duster',
        'Magnite',
        'Astor', 'ZS EV'
    ],
    // Mid-size & Full-size SUVs
    'SUV' => [
        'Grand Vitara', 'Gypsy',
        'Creta', 'Alcazar', 'Tucson',
        'Harrier', 'Safari', 'Safari Storme',
        'Bolero', 'Scorpio Classic', 'Scorpio-N', 'Thar', 'XUV500', 'XUV700',
        'Fortuner', 'Hilux',
        'Seltos',
        'Hector',
        'X1 sDrive18d/18i', 'X3 xDrive20d', 'X5 xDrive30d', 'X7 xDrive40i',
        'GLA 200/220d', 'GLC 300/220d', 'GLE 300d/450', 'GLS 400d/450',
        'Q3 40 TFSI', 'Q5 45 TFSI', 'Q7 55 TFSI', 'Q8',
        'Defender 90', 'Defender 110', 'Defender 130', 'Discovery Sport', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover Velar'
    ],
    // MUV / MPV / Van
    'MUV' => [
        'Ertiga', 'XL6', 'Invicto', 'Eeco', 'Omni',
        'Sumo',
        'Innova Crysta', 'Innova Hycross', 'Rumion', 'Qualis',
        'Carens', 'Carnival',
        'Triber'
    ]
];

$updateStmt = $pdo->prepare("UPDATE product_models SET body_type = ? WHERE name = ?");
$count = 0;
foreach ($car_types as $btype => $models) {
    foreach ($models as $mname) {
        $updateStmt->execute([$btype, $mname]);
        $count += $updateStmt->rowCount();
    }
}
echo "Updated $count car model body types.\n";

// 4. Populate Bikes (Category 27)
$bike_types = [
    'Scooter' => [
        'Activa', 'Dio', 'Access 125', 'Avenis 125', 'Burgman Street', 'Jupiter', 'Ntorq 125', 'Aerox 155', 'Fascino 125', 'RayZR 125', 'Destini 125', 'Pleasure Plus', 'Xoom 110'
    ],
    'Electric Scooter' => [
        '450 Apex Limited', '450S 2.9 kWh', '450X 2.9 kWh', '450X 3.7 kWh Pro', 'Rizta S 2.9 kWh', 'Rizta Z 3.7 kWh',
        'S1 Air 3 kWh', 'S1 Pro Gen 2 4 kWh', 'S1 X 2 kWh', 'S1 X 3 kWh', 'S1 X 4 kWh',
        'Chetak EV', 'iQube EV'
    ],
    'Cruiser' => [
        'Bullet 350', 'Classic 350', 'Continental GT 650', 'Electra 350', 'Guerrilla 450', 'Himalayan 450', 'Hunter 350', 'Interceptor 650', 'Meteor 350', 'Scram 411', 'Shotgun 650', 'Super Meteor 650', 'Thunderbird 350 / 350X',
        'Avenger', 'CB350 H\'ness', 'CB350RS', 'Jawa', 'Yezdi', 'Ronin 225'
    ],
    'Sports Bike' => [
        'Pulsar 220F', 'Pulsar N160', 'Pulsar N250', 'Pulsar NS125', 'Pulsar NS200', 'Pulsar NS400Z',
        'Karizma', 'Xtreme 125R', 'Xtreme 160R',
        'CB300R', 'Hornet 2.0',
        'Duke', 'RC', 'Adventure',
        'Gixxer', 'V-Strom SX',
        'Apache RR 310', 'Apache RTR 160', 'Apache RTR 200 4V',
        'F77 Mach 2 Original', 'F77 Mach 2 Recon',
        'YZF R15', 'MT-15', 'FZ'
    ],
    'Motorcycle' => [
        'Splendor Plus', 'HF Deluxe', 'Passion', 'Glamour', 'Xpulse 200',
        'Shine', 'SP 125', 'SP 160', 'Unicorn 160',
        'Platina', 'Pulsar 125', 'Pulsar 150', 'Dominar',
        'Raider 125', 'XL100', 'RX 100 / RX 135',
        'RV400 BRZ', 'RV400 Premium Fast Charger'
    ]
];

$b_count = 0;
foreach ($bike_types as $btype => $models) {
    foreach ($models as $mname) {
        $updateStmt->execute([$btype, $mname]);
        $b_count += $updateStmt->rowCount();
    }
}
echo "Updated $b_count bike model body types.\n";

// 5. Update existing products with model body_type
$pdo->exec("
    UPDATE products p
    JOIN product_models m ON p.model = m.name
    SET p.body_type = m.body_type
    WHERE p.body_type IS NULL OR p.body_type = ''
");
echo "Synced existing products' body_type.\n";
echo "Done!\n";
