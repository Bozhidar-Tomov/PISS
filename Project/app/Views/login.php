<section class="common-container login form-fields" aria-label="Login">
    <h1><?= $title ?></h1>
    <?php if (!empty($errors)): ?>
        <div class="status status-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
    <form method="post" action="<?= $basePath ?>/login" autocomplete="on">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" required autocomplete="username">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button class="btn btn-primary" type="submit">Login</button>
    </form>
    <p>Don't have an account? <a class="link" href="<?= $basePath ?>/register">Register here.</a></p>
</section>