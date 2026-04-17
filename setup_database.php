<?php
// Setup para canastamx (MySQL)
// Ejecuta en navegador: http://localhost/CanastaMX/setup_database.php

$host = 'localhost';
$dbName = 'canastamx';
$user = 'root';
$pass = '12345';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbName`; ");

    $sql = "
    CREATE TABLE IF NOT EXISTS productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        unidad VARCHAR(50) NOT NULL,
        categoria VARCHAR(50) NOT NULL,
        emoji VARCHAR(10) DEFAULT '🛒',
        precio_base DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS estados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) UNIQUE NOT NULL
    );
    CREATE TABLE IF NOT EXISTS tiendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL
    );
    CREATE TABLE IF NOT EXISTS precios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT,
        estado_id INT,
        tienda_id INT,
        precio DECIMAL(10,2),
        fecha DATE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (estado_id) REFERENCES estados(id) ON DELETE CASCADE,
        FOREIGN KEY (tienda_id) REFERENCES tiendas(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS alertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT,
        tipo ENUM('mayor','menor'),
        precio_limite DECIMAL(10,2),
        estado_id INT NULL,
        activa BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (estado_id) REFERENCES estados(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol ENUM('admin','user') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    );
    ";

    $pdo->exec($sql);

    // Inserción inicial:
    $productos = [
        ['Aceite Vegetal Comestible','946 ml (1 pieza)','Abarrotes','🫙',50.00],
        ['Arroz en Grano','1 kg','Abarrotes','🌾',26.00],
        ['Atún en Aceite','2 latas de 140 g','Enlatados','🐟',40.00],
        ['Azúcar Estándar','1 kg','Abarrotes','🍬',28.00],
        ['Carne de Res','1 kg','Carnes','🥩',175.00],
        ['Cebolla Blanca','1 kg','Verduras','🧅',28.00],
        ['Chile Jalapeño','1 kg','Verduras','🌶️',40.00],
        ['Carne de Cerdo','1 kg','Carnes','🐖',140.00],
        ['Frijol','1 kg','Abarrotes','🫘',42.00],
        ['Harina de Maíz','1 kg','Abarrotes','🌽',24.00],
        ['Huevo','1 kg','Lácteos','🥚',52.00],
        ['Leche','1 litro','Lácteos','🥛',28.00],
        ['Limón','1 kg','Frutas','🍋',40.00],
        ['Manzana','1 kg','Frutas','🍎',45.00],
        ['Papas','1 kg','Verduras','🥔',28.00],
        ['Sal','1 kg','Abarrotes','🧂',15.00],
        ['Tortillas','1 kg','Básicos','🫓',24.00],
        ['Plátano','1 kg','Frutas','🍌',22.00],
        ['Jitomate','1 kg','Verduras','🍅',35.00],
        ['Zanahoria','1 kg','Verduras','🥕',22.00],
        ['Pasta','1 kg','Abarrotes','🍝',30.00],
        ['Sopa Instantánea','1 paquete','Enlatados','🍜',12.00],
        ['Café','1 paquete (200 g)','Bebidas','☕',100.00],
        ['Pan de Caja','1 pieza (680 g)','Panadería','🍞',65.00],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO productos (nombre, unidad, categoria, emoji, precio_base) VALUES (?,?,?,?,?)");
    foreach ($productos as $p) {
        $stmt->execute($p);
    }

    $estados = [
        'Aguascalientes','Baja California','Baja California Sur','Campeche','Chiapas','Chihuahua','Ciudad de México','Coahuila','Colima','Durango','Estado de México','Guanajuato','Guerrero','Hidalgo','Jalisco','Michoacán','Morelos','Nayarit','Nuevo León','Oaxaca','Puebla','Querétaro','Quintana Roo','San Luis Potosí','Sinaloa','Sonora','Tabasco','Tamaulipas','Tlaxcala','Veracruz','Yucatán','Zacatecas'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO estados (nombre) VALUES (?)");
    foreach ($estados as $e) {
        $stmt->execute([$e]);
    }

    $tiendas = ['Walmart','Bodega Aurrera','Soriana','Chedraui','La Comer','MercadoLibre'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO tiendas (nombre) VALUES (?)");
    foreach ($tiendas as $t) {
        $stmt->execute([$t]);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (usuario, password) VALUES (?, ?)");
    $stmt->execute(['admin', password_hash('447jesus**', PASSWORD_DEFAULT)]);

    echo '<h2>Base de datos creada y datos seed exitoso.</h2>';
    echo '<p>Abre <a href="index.php">index.php</a> o <a href="login.php">login.php</a>.</p>';

} catch (PDOException $e) {
    echo '<h2>Error de DB:</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}