<section class="common-container register form-fields" aria-label="Register">
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
    <?php if (isset($_SESSION['error'])): ?>
        <div class="status status-error" role="alert">
            <ul>
                <?php if (is_array($_SESSION['error'])): ?>
                    <?php foreach ($_SESSION['error'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><?= htmlspecialchars($_SESSION['error']) ?></li>
                <?php endif; ?>
            </ul>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
    <form action="<?= $basePath ?>/register" method="post" autocomplete="on">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" autocomplete="username" value="<?= $old['username'] ?? '' ?>" required>

        <label for="password">Password:</label>
        <input type="password" name="password" id="password" autocomplete="new-password" required>

        <label for="gender">Gender:</label>
        <select name="gender" id="gender" required>
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
        </select>

        <label for="role">Role:</label>
        <select name="role" id="role" required>
            <option value="participant">Active Participant</option>
            <option value="viewer">Passive Viewer</option>
        </select>

        <label for="categories">Categories (comma-separated, e.g. VIP,Guest,Fan):</label>
        <input type="text" name="categories" id="categories" autocomplete="off">

        <button class="btn btn-primary" type="submit">Register</button>
    </form>
    <p>Already have an account? <a class="link" href="<?= $basePath ?>/login">Login here.</a></p>
</section>