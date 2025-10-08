<?php
$phpue_header = <<<HTML

    <title>Home</title>
    <meta name="description" content="PHPue leverages PHP server-side rendering, and can create affordable web applications">
    <meta name="keywords" content="PHP, VUE, PHPue">
    <meta name="author" content="Edward Patch">

HTML;

    $age = 40;

    $fruit = ["Banana", "Apple", "Cherry"];

    $counter = 20;

?>

    <h1>Home Page</h1>

    <div id="js">
        <p>JS Age: <span id="age"></span></p>
    </div>

    <div id="php">
        <p>PHP Age: <span><?= htmlspecialchars($age) ?></span></p>
    </div>

    <div id="jsFruit">
        <p>PHP Injected into JS Fruit Array: <span id="fruit"></span></p>
    </div>

    <div>
        <ul>
<?php foreach($fruit as $fru): ?>            <li ><?= htmlspecialchars($fru) ?></li><?php endforeach; ?>
        </ul>
    </div>

    <div>
        <p id="counter"></p>
    </div>

<script>

    let age = 27;
    let ageContainer = document.getElementById("age");
    ageContainer.innerText = age;

    let fruit = <?php echo is_string($fruit) ? '"' . addslashes($fruit) . '"' : json_encode($fruit); ?>;

    let fruitContainer = document.getElementById("fruit");
    fruitContainer.innerText = fruit;

    let counter = <?php echo is_string($counter) ? '"' . addslashes($counter) . '"' : json_encode($counter); ?>;
    let counterContainer = document.getElementById("counter");
    counterContainer.innerText = counter;


</script>
