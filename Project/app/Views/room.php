<?php
$id = $id ?? '';
$username = $username ?? 'Guest';
$points = $points ?? 0;
$role = $role ?? 'Participant';
$categoriesStr = $categoriesStr ?? 'None';
$reactionAccuracy = $reactionAccuracy ?? 0;
$currentCommand = 'Awaiting next command...';
$countdown = '';
$audienceIntensity = '';
$audienceVolume = '';
$responders = '';
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
?>

<main class="room-main" aria-label="Room View">
    <div class="cue-controls-fixed" aria-label="Cue Controls">
        <button id="flashCueToggle" class="cue-btn" aria-label="Toggle Flash Cue" type="button" title="Flash Cue">
            <span id="flashCueIcon">⚡</span>
        </button>
        <button id="beepCueToggle" class="cue-btn" aria-label="Toggle Beep Cue" type="button" title="Beep Cue">
            <span id="beepCueIcon">🔊</span>
        </button>
    </div>
    
    <div id="dailyQuoteContainer" class="daily-quote-container" style="display: none; justify-content: center; flex-direction: column; align-items: center; background: #f8f9fa; padding: 10px; margin: 10px auto; border-radius: 8px; max-width: 600px; text-align: center; border-left: 4px solid #339af0;">
        <!-- Quote will be injected here -->
    </div>

    <section class="command-panel" aria-label="Current Command">
        <div class="command-icon">🎬</div>
        <p class="command-text" id="commandText"><?= $currentCommand ?></p>
        <p class="countdown" id="countdownDisplay"><?= $countdown ?></p>
    </section>
    
    <section class="panels">
        <section class="panel user-panel" aria-label="Your Info">
            <h2 class="panel-title">Your Info</h2>
            <div class="user-profile">
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= $username ?></div>
                    <div class="user-role"><?= $role ?></div>
                </div>
            </div>
            <ul class="user-info-list">
                <li><span class="info-label">Points:</span> <span class="info-value"><?= $points ?></span></li>
                <li><span class="info-label">Categories:</span> <span class="info-value" id="userCategories"><?= $categoriesStr ?></span></li>
                <li><span class="info-label">Reaction Accuracy:</span> <span class="info-value" id="reactionAccuracy"><?= $reactionAccuracy ?>%</span></li>
            </ul>
            <button id="editCategoriesBtn" class="btn btn-secondary">Edit Categories</button>
        </section>
        
        <section class="panel audience-panel" aria-label="Audience Reaction">
            <h2 class="panel-title">Audience Reaction</h2>
            <div class="audience-metrics">
                <?php if (!file_exists(__DIR__ . '/../Database/sim_audience_on.flag')): ?>
                <div class="audience-metric">
                    <div class="metric-icon">🔥</div>
                    <div class="metric-details">
                        <div class="metric-label">Intensity</div>
                        <div class="metric-value" id="audienceIntensity"><?= $audienceIntensity ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="audience-metric">
                    <div class="metric-icon">🔊</div>
                    <div class="metric-details">
                        <div class="metric-label">Volume</div>
                        <div class="metric-value" id="audienceVolume"><?= $audienceVolume ?></div>
                    </div>
                </div>
                <div class="audience-metric">
                    <div class="metric-icon">👥</div>
                    <div class="metric-details">
                        <div class="metric-label">Responders</div>
                        <div class="metric-value" id="audienceResponders"><?= $responders ?></div>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="panel" aria-label="Transfer Points">
            <h2 class="panel-title">💸 Transfer Points</h2>

            <form class="form-fields" id="transferPointsForm" action="<?= $basePath ?>/api/transfer_points.php" method="POST" autocomplete="off" target="_self" onsubmit="if(window.event){window.event.preventDefault();return false;}">
                <input type="hidden" name="fromUserId" value="<?= htmlspecialchars($id) ?>">
                <label for="recipient">Recipient Username:</label>
                <input type="text" id="recipient" name="recipient" required>
                <label for="amount">Points:</label>
                <input type="number" id="amount" name="amount" min="1" max="<?= $points ?>" required>
                <label for="message">Message (optional):</label>
                <input type="text" id="message" name="message">
                <button class="btn btn-primary" type="submit">Send Points</button>
                <div class="form-feedback" aria-live="polite"></div>
            </form>
            <noscript><div style="color: #c92a2a; margin-top: 8px;">Note: Without JavaScript, the page will reload after transfer.</div></noscript>
        </section>
    </section>
</main>

<!-- Categories Edit Modal -->
<div id="categoriesModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Update Your Categories</h3>
        <p class="modal-description">Enter your categories separated by commas (e.g., Music, Sports, Technology)</p>
        <form id="updateCategoriesForm" class="form-fields">
            <label for="categoriesInput">Categories:</label>
            <input type="text" id="categoriesInput" name="categories" value="<?= htmlspecialchars($categoriesStr) ?>" required>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <div class="form-feedback" aria-live="polite"></div>
        </form>
    </div>
</div>

<div id="micRecordingIndicator" style="display:none; position: fixed; top: 12px; right: 18px; z-index: 10010; background: rgba(255,255,255,0.95); border-radius: 8px; padding: 6px 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); font-weight: bold; color: #c92a2a; align-items: center; gap: 0.5em;">
    <span style="display:inline-block; width: 12px; height: 12px; background: #c92a2a; border-radius: 50%; margin-right: 8px; box-shadow: 0 0 6px 2px #faa2a2;"></span>
    Recording...
</div>

<script>
    window.userId = "<?= isset($id) ? htmlspecialchars($id) : '' ?>";
    window.basePath = "<?= $basePath ?>";
    // Debug: Log transfer points data to console
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[DEBUG] window.userId:', window.userId);
        console.log('[DEBUG] window.basePath:', window.basePath);
        var transferForm = document.getElementById('transferPointsForm');
        if (transferForm) {
            var recipient = transferForm.querySelector('[name="recipient"]');
            var amount = transferForm.querySelector('[name="amount"]');
            var message = transferForm.querySelector('[name="message"]');
            console.log('[DEBUG] Transfer form fields:', {
                recipient: recipient ? recipient.value : undefined,
                amount: amount ? amount.value : undefined,
                message: message ? message.value : undefined
            });
        }
    });
</script>
<script src="<?= $basePath ?>/js/room.js"></script>