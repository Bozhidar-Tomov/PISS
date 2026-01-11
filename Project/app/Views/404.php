<?php
$title = "Page Not Found";
$viewStyle = "../css/views/404.css";
ob_start();
?>

<section class="common-container not-found" aria-label="404 Not Found">
    <h1 class="not-found-title">😵 404 - Page Not Found</h1>
    <p class="not-found-subtitle">Oops! This page marched to its own beat and got lost.</p>
    <p>Sorry, the page you are looking for does not exist.</p>
    <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
    <a class="link" href="<?= $basePath ?>/">Go back to the homepage</a>
</section>

<?php
$content = ob_get_clean();
include 'layout.php';
