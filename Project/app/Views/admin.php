<?php
require_once __DIR__ . '/../Database/Database.php';
use App\Database\Database;

$db = Database::getInstance();

// Check if simulated audience is enabled
$simAudienceEnabled = false;
try {
    $stmt = $db->query(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        ['sim_audience_on']
    );
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $simAudienceEnabled = $setting && $setting['setting_value'] === '1';
} catch (PDOException $e) {
    // Handle error silently
}
?>
<main class="admin-main">    
    <section class="panels">
        <section class="panel" aria-label="Send Command">
            <?php
                $statusTypes = ['error', 'warning', 'success'];
                foreach ($statusTypes as $type):
                    if (!empty($_SESSION[$type])):
            ?>

            <div class="status status-<?= $type ?>" role="alert">
                <ul>
                    <?php foreach ($_SESSION[$type] as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php unset($_SESSION[$type]); ?>
            </div>
            <?php
                endif;
                endforeach;
            ?>

            <h2 class="panel-title">Send Command</h2>
            <div id="simAudienceControl">
                <button id="simAudienceBtn" type="button" class="btn btn-secondary">
                    <span id="simAudienceBtnText"><?php echo $simAudienceEnabled ? 'Disable' : 'Enable'; ?></span> Simulated Audience
                </button>
            </div>
            <span id="simAudienceFeedback" style="color:#2b8a3e;"></span>
            <?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>
            <form id="commandForm" method="post" action="<?= $basePath ?>/admin/broadcast" class="form-fields">
                    <label for="commandType">Command Type:</label>
                    <select id="commandType" name="type" required>
                        <option value="applause">Applause</option>
                        <option value="cheer">Cheer</option>
                        <option value="boo">Boo</option>
                        <option value="murmur">Murmur</option>
                        <option value="stomp">Stomp</option>
                        <option value="silence">Silence</option>
                    </select>

                    <?php if ($simAudienceEnabled): ?>
                    <div id="intensitySliderWrapper">
                        <label for="intensity">Intensity (1-100):</label>
                        <div class="slider-container">
                            <input type="range" id="intensity" name="intensity" min="1" max="100" value="50">
                            <span id="intensityValue">50</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <label for="duration">Duration (seconds):</label>
                    <input type="number" id="duration" name="duration" min="1" max="30" value="5">

                    <label for="countdown">Countdown (seconds):</label>
                    <input type="number" id="countdown" name="countdown" min="0" max="10" value="3">

                    <label for="categories">Target Categories (comma-separated):</label>
                    <input type="text" id="categories" name="categories" placeholder="e.g., VIP,section-A,premium,student">
                    
                    <label for="gender">Target Gender:</label>
                    <select id="gender" name="gender">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>

                    <label for="message">Custom Message:</label>
                    <input type="text" id="message" name="message" placeholder="Optional instruction or message">

                    <div id="commandFormFeedback" class="form-feedback"></div>

                <button type="submit" class="btn btn-primary">Send Command</button>
            </form>
        </section>

        <!-- Live Feedback Panel -->
        <section class="panel" aria-label="Live Feedback">
            <h2 class="panel-title">Live Feedback</h2>
            <div class="connection-status">
                <div class="status-indicator" id="connectionStatus">
                    <span class="status-dot" id="statusDot"></span>
                    <span class="status-text" id="statusText">SSE Server: Checking status...</span>
                </div>
                <div class="last-command" id="lastCommandInfo">
                    No commands sent yet
                </div>
            </div>
            <div class="metrics">
                <div class="metric">
                    <label>Active Users:</label>
                    <span id="activeUsers">0</span>
                </div>
                <div class="metric">
                    <label>Current Volume:</label>
                    <span id="currentVolume">0 dB</span>
                </div>
                <div class="metric">
                    <label>Response Rate:</label>
                    <span id="responseRate">0%</span>
                </div>
            </div>
        </section>
    </section>
</main>

<script>
    window.basePath = "<?= defined('BASE_PATH') ? BASE_PATH : '' ?>";
</script>
<script src="<?= $basePath ?>/js/admin.js"></script>