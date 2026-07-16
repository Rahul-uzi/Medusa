<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE food_items SET image_url = 'thai_curry_green_red.jpg' WHERE id = 40")->execute();
    echo "Updated ID 40 with image thai_curry_green_red.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'gong_bao_bowl.jpg' WHERE id = 26")->execute();
    echo "Updated ID 26 with image gong_bao_bowl.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'minced_basil_chicken.jpg' WHERE id = 39")->execute();
    echo "Updated ID 39 with image minced_basil_chicken.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'seoul_bibimbap.jpg' WHERE id = 23")->execute();
    echo "Updated ID 23 with image seoul_bibimbap.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'shanghai_bowl.jpg' WHERE id = 25")->execute();
    echo "Updated ID 25 with image shanghai_bowl.jpg\n";

    $pdo->commit();
    echo "=== MEALS IMAGES SYNCED IN DB ===\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage() . "\n");
}
?>