**PHPue Framework 🚀**
A powerful PHP framework that brings Vue-inspired syntax to server-side rendering with hot reload, component system, and seamless PHP-JavaScript integration.

**🌟 What is PHPue?**
PHPue combines the simplicity of PHP with Vue-like templating syntax to create fast, scalable web applications with server-side rendering. It offers the developer experience of modern frameworks with the performance and simplicity of traditional PHP.

**🎯 Features**
🏗️ Component System
```html
<!-- components/Navbar.pvue -->
<template>
    <nav class="navbar">
        <ul>
            <li p-for="$item in $navItems">
                <a href="{{ $item.url }}">{{ $item.title }}</a>
            </li>
        </ul>
    </nav>
</template>

<cscript>
    // Client-side JavaScript for interactivity
    document.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', handleNavigation);
    });
</cscript>
```

**📦 Import System**

Component Importing

```html
<script setup>
    @require Navbar 'components/Navbar.pvue';
    @require Footer 'components/Footer.pvue';
</script>
```

**View Importing (with name)**

```html
<script setup>
    #require Home 'views/index.pvue';
    #require About 'views/about.pvue';
    #require Contact 'views/contact.pvue';
</script>
```

**View Importing (auto-named)**

```html
<script setup>
    #require 'views/index.pvue';
    #require 'views/about.pvue';
</script>
```

**Traditional PHP Includes**

```html
<script setup>
    require_once 'config/database.php';
    require 'helpers/functions.php';
</script>
```

**🎨 Directives**


**p-if** - Conditional Rendering

```html
<template>
    <div p-if="$user.isLoggedIn">
        Welcome back, {{ $user.name }}!
    </div>
    
    <div p-if="count($products) > 0">
        <p>Showing {{ count($products) }} products</p>
    </div>
    
    <div p-if="!$user.isLoggedIn">
        <a href="/login">Please log in</a>
    </div>
</template>
```

**p-for** - List Rendering

```html
<template>
    <ul>
        <li p-for="$user in $users" class="user-item">
            <strong>{{ $user.name }}</strong> - {{ $user.email }}
        </li>
    </ul>
    
    <div p-for="$product in $featuredProducts" class="product-card">
        <h3>{{ $product.title }}</h3>
        <p>{{ $product.description }}</p>
        <span class="price">${{ $product.price }}</span>
    </div>
</template>
```

**p-model** - Two-Way Data Binding

```html
<template>
    <form method="POST">
        <div class="form-group">
            <label>Name:</label>
            <input type="text" p-model="$name" class="form-control">
            <small>Current value: {{ $name }}</small>
        </div>
        
        <div class="form-group">
            <label>Email:</label>
            <input type="email" p-model="$email" class="form-control">
        </div>
        
        <div class="form-group">
            <label>Message:</label>
            <textarea p-model="$message" class="form-control" rows="4"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</template>

<script>
    $name = "";
    $email = "";
    $message = "";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Process form data...
    }
</script>
```

**📄 File Structure**
App.pvue (Root Component)
```html
<!-- Author: Your Name -->
<script setup>
    @require Navbar 'components/Navbar.pvue';
    #require Home 'views/index.pvue';
    #require About 'views/about.pvue';
    #require Contact 'views/contact.pvue';

    $routes = phpue_navigation();
</script>

<header>
    <!-- Global headers, styles, scripts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
</header>

<template>
    <Navbar></Navbar>
    <View></View> <!-- Dynamic page content injection -->
</template>
```

**View File Structure**

```html
<script>
    // Server-side PHP code
    $pageTitle = "Home Page";
    $featuredProducts = [
        ['name' => 'Product 1', 'price' => 29.99],
        ['name' => 'Product 2', 'price' => 39.99]
    ];
    $user = ['name' => 'John Doe', 'isAdmin' => true];
</script>

<header>
    <!-- Page-specific headers -->
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="Welcome to our amazing website">
    <meta name="keywords" content="php, vue, framework">
</header>

<template>
    <div class="container">
        <h1>{{ $pageTitle }}</h1>
        
        <div p-if="$user.isAdmin" class="admin-panel">
            <button class="btn btn-warning">Admin Controls</button>
        </div>
        
        <div class="products">
            <div p-for="$product in $featuredProducts" class="product-card">
                <h3>{{ $product.name }}</h3>
                <p class="price">${{ $product.price }}</p>
            </div>
        </div>
    </div>
</template>

<cscript>
    // Client-side JavaScript
    console.log("Page loaded successfully!");
    
    // Access PHP variables in JavaScript
    let products = {{ $featuredProducts }};
    let user = {{ $user }};
    
    console.log("Products:", products);
    console.log("User:", user);
    
    // Add interactivity
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', () => {
            card.classList.toggle('selected');
        });
    });
</cscript>
```

**🔥 Hot Reload Development**

```bash
# Start development server with hot reload
php server.php
```

# Visit: http://localhost:3000/
# Changes to .pvue files automatically reload the page!
🚀 Production Build
```bash
# Compile all .pvue files to .php for production
php server.php build
```

# Deploy the 'dist/' directory to your production server

🛣️ Routing System
Clean URLs: yoursite.com/about automatically loads views/about.pvue

**Automatic Routing:** All files in views/ become routes

**Navigation Helper:** phpue_navigation() returns all available routes

🔄 PHP-JavaScript Integration
Seamless Variable Passing

```html
<script>
    $userData = ['name' => 'John', 'age' => 30, 'premium' => true];
    $items = ['Apple', 'Banana', 'Cherry'];
    $counter = 42;
</script>

<template>
    <!-- PHP in HTML -->
    <p>Welcome, {{ $userData.name }}!</p>
    <p>Item count: {{ count($items) }}</p>
</template>

<cscript>
    // PHP variables in JavaScript
    let user = {{ $userData }};        // Object: {name: "John", age: 30, premium: true}
    let items = {{ $items }};          // Array: ["Apple", "Banana", "Cherry"]  
    let counter = {{ $counter }};      // Number: 42
    
    console.log(user.name);            // "John"
    console.log(items.length);         // 3
    console.log(counter + 10);         // 52
</cscript>
```

**📁 Project Structure**


text
your-project/
├── App.pvue                 # Root application component
├── server.php              # Development server
├── conversion.php          # PHPue compiler
├── components/             # Reusable components
│   ├── Navbar.pvue
│   ├── Footer.pvue
│   └── UserCard.pvue
├── views/                  # Page views
│   ├── index.pvue
│   ├── about.pvue
│   └── contact.pvue
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   └── images/
└── dist/                   # Compiled PHP files (production)

**🚀 Quick Start**
Create App.pvue

```html
<script setup>
    @require Header 'components/Header.pvue';
    #require 'views/index.pvue';
</script>

<header>
    <title>My PHPue App</title>
</header>

<template>
    <Header></Header>
    <View></View>
</template>
```

Create a view:

```html
<!-- views/index.pvue -->
<script>
    $message = "Hello PHPue!";
    $items = ['Learn', 'Build', 'Deploy'];
</script>

<template>
    <div class="container">
        <h1>{{ $message }}</h1>
        <ul>
            <li p-for="$item in $items">{{ $item }}</li>
        </ul>
    </div>
</template>
```

Start development:

```bash
php server.php
```

💡 Why PHPue?
✅ Server-Side Rendering - Better SEO and performance

✅ Hot Reload - Instant development feedback

✅ Vue-Inspired Syntax - Familiar and intuitive

✅ PHP Power - Full access to PHP ecosystem

✅ Component-Based - Reusable and maintainable

✅ Auto Routing - File-based routing system

✅ PHP+JS Integration - Seamless variable passing

✅ Production Ready - Build system for deployment

🎉 Get Building!
PHPue gives you the best of both worlds: the simplicity and power of PHP with the modern developer experience of component-based frameworks. Start building your next amazing web application today! 🚀

PHPue - Server-side rendering with Vue-like syntax. Fast, simple, powerful.