<?php
require_once __DIR__ . '/../api/config.php';

$menu_items = [
    // INDIAN SPECIALTIES
    [
        'name' => 'Butter Chicken',
        'description' => 'Tender chicken simmered in a rich, creamy tomato gravy with aromatic spices and finished with butter.',
        'price' => 450.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1603894584373-5ac82b2ae398?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Chicken Biryani',
        'description' => 'Fragrant basmati rice layered with spiced chicken, caramelized onions, and saffron, cooked in dum style.',
        'price' => 350.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Dal Makhani',
        'description' => 'Slow-cooked black lentils in a rich, buttery tomato cream sauce, simmered overnight for deep flavor.',
        'price' => 280.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Rogan Josh',
        'description' => 'Kashmiri lamb curry with aromatic spices, saffron, and dried ginger - a royal delicacy.',
        'price' => 420.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Palak Paneer',
        'description' => 'Cubes of cottage cheese in a smooth, spiced spinach gravy with garlic and ginger.',
        'price' => 320.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Samosa',
        'description' => 'Crispy golden pastry filled with spiced potatoes and peas, served with mint and tamarind chutney.',
        'price' => 180.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Garlic Naan',
        'description' => 'Soft, leavened bread baked in tandoor, topped with garlic butter and fresh coriander.',
        'price' => 80.00,
        'category' => 'indian',
        'image_url' => 'https://images.unsplash.com/photo-1565557623262-b51c2513a641?w=400&h=300&fit=crop'
    ],
    
    // ITALIAN
    [
        'name' => 'Margherita Pizza',
        'description' => 'Classic wood-fired pizza with San Marzano tomato sauce, fresh mozzarella, basil, and olive oil.',
        'price' => 350.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Pepperoni Pizza',
        'description' => 'Loaded with spicy pepperoni, mozzarella cheese, and our signature tomato sauce on a crispy crust.',
        'price' => 420.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Pasta Alfredo',
        'description' => 'Creamy fettuccine Alfredo with parmesan, garlic, and butter. Rich, velvety, and indulgent.',
        'price' => 380.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Pasta Arrabiata',
        'description' => 'Spicy tomato-based pasta with garlic, chili flakes, and fresh herbs. Bold and fiery.',
        'price' => 350.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Classic Lasagna',
        'description' => 'Layers of pasta, bolognese sauce, bechamel, mozzarella, and parmesan, baked to perfection.',
        'price' => 450.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Bruschetta',
        'description' => 'Toasted sourdough topped with fresh tomatoes, basil, garlic, olive oil, and balsamic glaze.',
        'price' => 250.00,
        'category' => 'italian',
        'image_url' => 'https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?w=400&h=300&fit=crop'
    ],
    
    // ASIAN
    [
        'name' => 'Sushi Roll (8 pcs)',
        'description' => 'Fresh maki rolls with seasoned rice, nori, and premium fillings. Served with soy, wasabi, and ginger.',
        'price' => 550.00,
        'category' => 'asian',
        'image_url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Pad Thai',
        'description' => 'Stir-fried rice noodles with tamarind sauce, bean sprouts, peanuts, lime, and your choice of protein.',
        'price' => 380.00,
        'category' => 'asian',
        'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Dim Sum (6 pcs)',
        'description' => 'Steamed dumplings with delicate filling, served with soy-chili dipping sauce.',
        'price' => 320.00,
        'category' => 'asian',
        'image_url' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Ramen Bowl',
        'description' => 'Rich tonkotsu broth with ramen noodles, chashu pork, soft-boiled egg, nori, and scallions.',
        'price' => 420.00,
        'category' => 'asian',
        'image_url' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Spring Rolls (4 pcs)',
        'description' => 'Crispy rolls stuffed with vegetables or meat, served with sweet chili dipping sauce.',
        'price' => 220.00,
        'category' => 'asian',
        'image_url' => 'https://images.unsplash.com/photo-1604909052743-94e838986d24?w=400&h=300&fit=crop'
    ],
    
    // AMERICAN
    [
        'name' => 'Classic Burger',
        'description' => 'Juicy grilled patty with fresh lettuce, tomato, onion, cheese, and our secret sauce in a toasted bun.',
        'price' => 350.00,
        'category' => 'american',
        'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'BBQ Ribs',
        'description' => 'Slow-cooked pork ribs glazed with smoky BBQ sauce, served with coleslaw and fries.',
        'price' => 550.00,
        'category' => 'american',
        'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Mac & Cheese',
        'description' => 'Creamy elbow macaroni in a blend of cheddar, mozzarella, and parmesan with a crispy breadcrumb top.',
        'price' => 320.00,
        'category' => 'american',
        'image_url' => 'https://images.unsplash.com/photo-1543339494-b4cd4f7ba686?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Chicken Wings (6 pcs)',
        'description' => 'Crispy fried chicken wings tossed in your choice of sauce. Served with ranch or blue cheese dip.',
        'price' => 380.00,
        'category' => 'american',
        'image_url' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Club Sandwich',
        'description' => 'Triple-decker sandwich with roasted turkey, bacon, lettuce, tomato, and mayo on toasted bread.',
        'price' => 320.00,
        'category' => 'american',
        'image_url' => 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?w=400&h=300&fit=crop'
    ],
    
    // DESSERTS
    [
        'name' => 'Gulab Jamun (3 pcs)',
        'description' => 'Deep-fried milk solid dumplings soaked in rose-scented sugar syrup. Warm, soft, and irresistible.',
        'price' => 150.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Tiramisu',
        'description' => 'Classic Italian dessert with layers of coffee-soaked ladyfingers, mascarpone cream, and cocoa.',
        'price' => 320.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Ice Cream Sundae',
        'description' => 'Three scoops of premium ice cream with hot fudge, caramel, whipped cream, and a cherry on top.',
        'price' => 220.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1551024601-bec78aea704b?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Mochi Ice Cream (3 pcs)',
        'description' => 'Chewy Japanese rice dough wrapped around creamy ice cream. Available in assorted flavors.',
        'price' => 280.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'New York Cheesecake',
        'description' => 'Silky cream cheese filling on a buttery graham cracker crust, baked to perfection.',
        'price' => 350.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1482049016688-2d3e1b311543?w=400&h=300&fit=crop'
    ],
    [
        'name' => 'Chocolate Lava Cake',
        'description' => 'Warm chocolate cake with a molten center, served with vanilla ice cream and chocolate shavings.',
        'price' => 380.00,
        'category' => 'desserts',
        'image_url' => 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=400&h=300&fit=crop'
    ]
];

try {
    $pdo->exec("TRUNCATE TABLE food_items");
    
    $stmt = $pdo->prepare("INSERT INTO food_items (name, description, price, category, image_url, is_available) VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($menu_items as $item) {
        $stmt->execute([
            $item['name'],
            $item['description'],
            $item['price'],
            $item['category'],
            $item['image_url']
        ]);
    }
    
    echo "Successfully imported " . count($menu_items) . " Medusa menu items into MySQL food_items table.\n";
} catch (Exception $e) {
    echo "Error during import: " . $e->getMessage() . "\n";
}
?>
