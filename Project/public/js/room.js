// Get user ID and base path from global variables
const userId = window.userId || "";
const basePath = window.basePath ? window.basePath.replace(/\/+$/, '') : "";

// Simulated audience variables
let simAudienceEnabled = false; // Whether simulated audience is on
let currentSimAudio = [];

// SSE connection variables
let evtSource = null;
let reconnectAttempts = 0;
let reconnectTimer = null;
let currentCommandId = null;
let processedCommands = new Set(); // Track processed commands to prevent duplicates

// Command display timeouts
let clearInstructionTimeout = null;
let countdownTimeout = null;
let resetCommandTimeout = null;

// Microphone recording variables
let micStream = null;
let audioContext = null;
let mediaRecorder = null;
let audioChunks = [];
let micRecordingTimeout = null;
let micIndicator = null;

// Categories modal elements
let categoriesModal = null;
let closeModalBtn = null;
let updateCategoriesForm = null;

/**
 * Establishes Server-Sent Events connection
 */
function connectSSE() {
  if (evtSource) {
    evtSource.close();
  }

  evtSource = new EventSource(`${basePath}/sse`);

  evtSource.onopen = function () {
    console.log("SSE connection established");
    reconnectAttempts = 0;
  };


  evtSource.onerror = function (e) {
    // Only log error if it's not a normal connection close
    if (evtSource.readyState !== EventSource.CLOSED) {
      console.warn("SSE connection error", e);
    }
    evtSource.close();
    evtSource = null;

    if (reconnectAttempts < 10) {
      const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
      console.log(`Attempting to reconnect in ${delay / 1000} seconds...`);

      clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(() => {
        reconnectAttempts++;
        connectSSE();
      }, delay);
    } else {
      console.warn("Max reconnection attempts reached. Please refresh the page to reconnect.");
      // Don't show alert, just log the warning
    }
  };

  // Event listeners
  evtSource.addEventListener("connected", function (event) {
    console.log("Connected to SSE server", JSON.parse(event.data));
  });

  evtSource.addEventListener("heartbeat", function (event) {
    const data = JSON.parse(event.data);
    console.log("Heartbeat received", data);

    if (data && typeof data.activeUsers !== "undefined") {
      const respondersElement = document.getElementById("audienceResponders");
      if (respondersElement) {
        respondersElement.textContent = data.activeUsers;

        // Add animation effect to the metric
        const metricContainer = respondersElement.closest('.audience-metric');
        if (metricContainer) {
          metricContainer.classList.add('metric-updated');
          setTimeout(() => {
            metricContainer.classList.remove('metric-updated');
          }, 1000);
        }
      }
    }
  });

  evtSource.addEventListener("command", async function (event) {
    const data = JSON.parse(event.data);
    console.log("Command received:", data);

    // Skip if we've already processed this command
    if (!data.id || processedCommands.has(data.id)) {
      console.log(`Skipping duplicate command: ${data.id}`);
      return;
    }

    // Mark this command as processed
    processedCommands.add(data.id);
    currentCommandId = data.id;

    // Limit the size of the processedCommands set to prevent memory issues
    if (processedCommands.size > 50) {
      const iterator = processedCommands.values();
      processedCommands.delete(iterator.next().value);
    }

    // Handle transfer messages
    if (data.type === 'transfer_message') {
      console.log('Received transfer message:', data);
      showTransferMessage(data.fromUsername, data.message, data.amount);
      return;
    }

    // Update the command text with command type
    const commandElement = document.getElementById("commandText");
    commandElement.textContent = data.type;

    // Display the admin message if present
    if (data.message) {
      // Check if message element already exists, if not create it
      let messageElement = document.getElementById("adminMessage");
      if (!messageElement) {
        messageElement = document.createElement("p");
        messageElement.id = "adminMessage";
        messageElement.className = "admin-message";
        commandElement.parentNode.insertBefore(messageElement, document.getElementById("countdownDisplay"));
      }
      messageElement.textContent = data.message;
      messageElement.style.display = "block";
    } else {
      // Hide message element if no message
      const existingMessage = document.getElementById("adminMessage");
      if (existingMessage) {
        existingMessage.style.display = "none";
      }
    }

    // Clear any previous timeouts
    clearCommandTimeouts();

    // Handle countdown
    let countdownSeconds = 0;
    if (data.countdown) {
      countdownSeconds = parseInt(data.countdown);
      startCountdown(countdownSeconds);
    } else {
      document.getElementById("countdownDisplay").textContent = "Now!";
    }

    // Handle microphone recording
    let duration = (typeof data.duration === "number" && data.duration > 0) ? data.duration : 0;

    // Schedule reset of command display after duration + countdown time
    const totalTime = (countdownSeconds + Math.max(duration, 1)) * 1000; // Ensure at least 1 second after countdown
    resetCommandTimeout = setTimeout(() => {
      resetCommandDisplay();
      // Stop any simulated audio still playing
      stopSimulatedAudio();
    }, totalTime);

    if (duration > 0) {
      // Schedule simulated audience sounds after countdown reaches 0
      if (simAudienceEnabled) {
        setTimeout(() => {
          playSimulatedAudience(data.type, data.intensity || 50, duration);
        }, countdownSeconds * 1000);
      }
      setTimeout(() => {
        startMicRecording(duration, data.id, data.type);
      }, countdownSeconds * 1000);
    }
  });
}

/**
 * Clears all command-related timeouts
 */
function clearCommandTimeouts() {
  if (clearInstructionTimeout) {
    clearTimeout(clearInstructionTimeout);
    clearInstructionTimeout = null;
  }
  if (countdownTimeout) {
    clearTimeout(countdownTimeout);
    countdownTimeout = null;
  }
  if (resetCommandTimeout) {
    clearTimeout(resetCommandTimeout);
    resetCommandTimeout = null;
  }
}

/**
 * Resets command display after command is complete
 */
function resetCommandDisplay() {
  const commandElement = document.getElementById("commandText");
  const countdownElement = document.getElementById("countdownDisplay");
  const messageElement = document.getElementById("adminMessage");

  // Reset command text
  commandElement.textContent = "Awaiting next command...";

  // Reset countdown display
  countdownElement.textContent = "";

  // Hide admin message if present
  if (messageElement) {
    messageElement.style.display = "none";
  }
}

/**
 * Initializes categories modal functionality
 */
function initCategoriesModal() {
  categoriesModal = document.getElementById('categoriesModal');
  closeModalBtn = document.querySelector('.close-modal');
  updateCategoriesForm = document.getElementById('updateCategoriesForm');
  const editCategoriesBtn = document.getElementById('editCategoriesBtn');

  if (!categoriesModal || !closeModalBtn || !updateCategoriesForm || !editCategoriesBtn) {
    return;
  }

  // Open modal when edit button is clicked
  editCategoriesBtn.addEventListener('click', () => {
    categoriesModal.style.display = 'block';
  });

  // Close modal when X is clicked
  closeModalBtn.addEventListener('click', () => {
    categoriesModal.style.display = 'none';
  });

  // Close modal when clicking outside of it
  window.addEventListener('click', (e) => {
    if (e.target === categoriesModal) {
      categoriesModal.style.display = 'none';
    }
  });

  // Handle form submission
  updateCategoriesForm.addEventListener('submit', (e) => {
    e.preventDefault();
    updateUserCategories();
  });
}

/**
 * Updates user categories via AJAX
 */
function updateUserCategories() {
  const formData = new FormData(updateCategoriesForm);
  const feedbackElement = updateCategoriesForm.querySelector('.form-feedback');

  feedbackElement.textContent = 'Updating...';
  feedbackElement.className = 'form-feedback info';

  fetch(`${basePath}/api/v1/user/categories`, {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        feedbackElement.textContent = data.message;
        feedbackElement.className = 'form-feedback success';

        // Update the categories display in the user info panel
        const categoriesDisplay = document.getElementById('userCategories');
        if (categoriesDisplay) {
          categoriesDisplay.textContent = data.categories.join(', ');
        }

        // Close modal after a short delay
        setTimeout(() => {
          categoriesModal.style.display = 'none';
          feedbackElement.textContent = '';
          feedbackElement.className = 'form-feedback';
        }, 1500);
      } else {
        feedbackElement.textContent = data.error || 'An error occurred';
        feedbackElement.className = 'form-feedback error';
      }
    })
    .catch(error => {
      console.error('Error updating categories:', error);
      feedbackElement.textContent = 'Network error. Please try again.';
      feedbackElement.className = 'form-feedback error';
    });
}

// Function to show transfer message notification
function showTransferMessage(fromUsername, message, amount) {
  console.log('showTransferMessage called with:', { fromUsername, message, amount });

  // Create or get the transfer message container
  let transferContainer = document.getElementById('transferMessageContainer');
  if (!transferContainer) {
    console.log('Creating new transfer message container');
    transferContainer = document.createElement('div');
    transferContainer.id = 'transferMessageContainer';
    transferContainer.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.3);
      z-index: 10000;
      max-width: 300px;
      font-family: 'Arial', sans-serif;
      transform: translateX(400px);
      transition: transform 0.3s ease-out;
    `;
    document.body.appendChild(transferContainer);
  }

  // Update the content
  transferContainer.innerHTML = `
    <div style="margin-bottom: 8px; font-weight: bold; font-size: 16px;">
      💰 +${amount} points from ${fromUsername}
    </div>
    <div style="font-size: 14px; line-height: 1.4;">
      "${message}"
    </div>
  `;

  // Show the message
  transferContainer.style.transform = 'translateX(0)';
  console.log('Transfer message displayed');

  // Hide after 5 seconds
  setTimeout(() => {
    transferContainer.style.transform = 'translateX(400px)';
    setTimeout(() => {
      if (transferContainer.parentNode) {
        transferContainer.parentNode.removeChild(transferContainer);
      }
    }, 300);
  }, 5000);
}

function createFlashOverlay() {
  let overlay = document.getElementById('flashCueOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'flashCueOverlay';
    overlay.style.position = 'fixed';
    overlay.style.top = 0;
    overlay.style.left = 0;
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.background = 'white';
    overlay.style.opacity = 0;
    overlay.style.zIndex = 9999;
    overlay.style.pointerEvents = 'none';
    overlay.style.transition = 'opacity 0.2s';
    document.body.appendChild(overlay);
  }
  return overlay;
}

/**
 * Triggers visual flash cue
 */
function triggerFlash() {
  const overlay = createFlashOverlay();
  overlay.style.opacity = 0.3;
  setTimeout(() => {
    overlay.style.opacity = 0;
  }, 180);
}

/**
 * Triggers audio beep cue
 */
function triggerBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.value = 880;
    g.gain.value = 0.2;
    o.connect(g);
    g.connect(ctx.destination);
    o.start();
    setTimeout(() => {
      o.stop();
      ctx.close();
    }, 150);
  } catch (e) {
    console.error('Error triggering beep:', e);
  }
}

/**
 * Gets user cue preferences from localStorage
 */
function getCuePrefs() {
  return {
    flash: localStorage.getItem('flashCueEnabled') !== 'false',
    beep: localStorage.getItem('beepCueEnabled') !== 'false',
  };
}

/**
 * Sets user cue preferences in localStorage
 */
function setCuePrefs(flash, beep) {
  localStorage.setItem('flashCueEnabled', flash);
  localStorage.setItem('beepCueEnabled', beep);
}

/**
 * Updates cue control button styling based on current preferences
 */
function updateCueIcons() {
  const flashBtn = document.getElementById('flashCueToggle');
  const beepBtn = document.getElementById('beepCueToggle');

  if (!flashBtn || !beepBtn) return;

  const flashIcon = document.getElementById('flashCueIcon');
  const beepIcon = document.getElementById('beepCueIcon');
  const prefs = getCuePrefs();

  if (prefs.flash) {
    flashBtn.classList.add('cue-on');
    flashBtn.classList.remove('cue-off');
    if (flashIcon) flashIcon.style.opacity = 1;
    flashBtn.style.background = '#ffe066';
    flashBtn.style.border = '2px solid #ffd700';
  } else {
    flashBtn.classList.remove('cue-on');
    flashBtn.classList.add('cue-off');
    if (flashIcon) flashIcon.style.opacity = 0.4;
    flashBtn.style.background = '#f8f9fa';
    flashBtn.style.border = '2px solid #ccc';
  }

  if (prefs.beep) {
    beepBtn.classList.add('cue-on');
    beepBtn.classList.remove('cue-off');
    if (beepIcon) beepIcon.style.opacity = 1;
    beepBtn.style.background = '#ffe066';
    beepBtn.style.border = '2px solid #ffd700';
  } else {
    beepBtn.classList.remove('cue-on');
    beepBtn.classList.add('cue-off');
    if (beepIcon) beepIcon.style.opacity = 0.4;
    beepBtn.style.background = '#f8f9fa';
    beepBtn.style.border = '2px solid #ccc';
  }
}

/**
 * Sets up cue control buttons and event listeners
 */
function setupCueControls() {
  const flashBtn = document.getElementById('flashCueToggle');
  const beepBtn = document.getElementById('beepCueToggle');

  if (!flashBtn || !beepBtn) return;

  // Set initial state
  updateCueIcons();

  flashBtn.addEventListener('click', () => {
    const prefs = getCuePrefs();
    setCuePrefs(!prefs.flash, prefs.beep);
    updateCueIcons();
  });

  beepBtn.addEventListener('click', () => {
    const prefs = getCuePrefs();
    setCuePrefs(prefs.flash, !prefs.beep);
    updateCueIcons();
  });
}

/**
 * Starts countdown timer and displays it
 */
function startCountdown(seconds) {
  let countdown = parseInt(seconds);
  const countdownElement = document.getElementById("countdownDisplay");

  if (!countdownElement) return;

  countdownElement.textContent = countdown;

  // Clear any existing interval
  if (countdownTimeout) {
    clearInterval(countdownTimeout);
  }

  countdownTimeout = setInterval(() => {
    countdown--;

    if (countdown <= 0) {
      clearInterval(countdownTimeout);
      countdownTimeout = null;
      countdownElement.textContent = "Now!";

      // Trigger cues when countdown ends
      const prefs = getCuePrefs();
      if (prefs.flash) triggerFlash();
      if (prefs.beep) triggerBeep();
    } else {
      countdownElement.textContent = countdown;
    }
  }, 1000);
}

/**
 * Shows or hides microphone recording indicator
 */
function showMicIndicator(show) {
  if (!micIndicator) micIndicator = document.getElementById('micRecordingIndicator');
  if (micIndicator) micIndicator.style.display = show ? 'flex' : 'none';
}

/**
 * Starts microphone recording for specified duration
 */
async function startMicRecording(durationSec, commandId, commandType) {
  // Clean up any previous recording session
  cleanupMicRecording();

  try {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      console.error('Microphone access is not supported in this browser.');
      return;
    }

    // Request mic access
    micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
    mediaRecorder = new MediaRecorder(micStream);
    audioChunks = [];

    mediaRecorder.ondataavailable = e => {
      if (e.data.size > 0) audioChunks.push(e.data);
    };

    mediaRecorder.onstop = async () => {
      showMicIndicator(false);
      const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
      // Analyze audio
      const analysis = await analyzeAudioBlob(audioBlob, audioContext.sampleRate, commandType);
      // Update participant's own fields
      if (document.getElementById('reactionAccuracy')) {
        document.getElementById('reactionAccuracy').textContent = analysis.reactionAccuracy + '%';
      }
      if (document.getElementById('audienceIntensity')) {
        document.getElementById('audienceIntensity').textContent = analysis.intensity;
      }
      if (document.getElementById('audienceVolume')) {
        document.getElementById('audienceVolume').textContent = analysis.volume;
      }
      // Send results to server
      sendMicResults(analysis);
      // Clean up
      if (micStream) {
        micStream.getTracks().forEach(track => track.stop());
        micStream = null;
      }
      if (audioContext) {
        audioContext.close().catch(console.error);
        audioContext = null;
      }

      // Clean up
      cleanupMicRecording();
    };

    mediaRecorder.start();
    showMicIndicator(true);

    // Stop recording after specified duration
    micRecordingTimeout = setTimeout(() => {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
      }
    }, durationSec * 1000);
  } catch (err) {
    console.error('Microphone access denied or error:', err);
    showMicIndicator(false);
  }
}

/**
 * Cleans up microphone recording resources
 */
function cleanupMicRecording() {
  if (micRecordingTimeout) {
    clearTimeout(micRecordingTimeout);
    micRecordingTimeout = null;
  }

  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop();
  }

  if (micStream) {
    micStream.getTracks().forEach(track => track.stop());
    micStream = null;
  }

  if (audioContext) {
    audioContext.close().catch(console.error);
    audioContext = null;
  }

  mediaRecorder = null;
  audioChunks = [];
}

/**
 * Analyzes audio blob for intensity, volume, and reaction accuracy
 */
async function analyzeAudioBlob(blob, sampleRate, commandType) {
  try {
    // Basic analysis: get average volume (RMS), peak, and duration
    const arrayBuffer = await blob.arrayBuffer();
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const audioBuffer = await ctx.decodeAudioData(arrayBuffer);
    const data = audioBuffer.getChannelData(0);
    let sum = 0, peak = 0, startIdx = -1, endIdx = -1;
    for (let i = 0; i < data.length; i++) {
      const abs = Math.abs(data[i]);
      sum += abs * abs;
      if (abs > 0.05 && abs > peak) peak = abs;
      if (startIdx === -1 && abs > 0.02) startIdx = i;
      if (abs > 0.02) endIdx = i;
    }
    const rms = Math.sqrt(sum / data.length);
    const duration = audioBuffer.duration;
    // Debug: log calculated values
    // console.log('RMS:', rms, 'Peak:', peak, 'Duration:', duration);
    let reactionAccuracy = 0;
    if (commandType === 'silence') {
      // Special logic: high accuracy if very little sound
      if (rms < 0.005) {
        reactionAccuracy = 100;
      } else {
        // Reaction accuracy: how soon after recording started did the sound start?
        if (startIdx !== -1) {
          const startSec = startIdx / sampleRate;
          reactionAccuracy = Math.max(0, 1 - startSec / duration); // 1 = instant, 0 = very late
          reactionAccuracy = Math.round(reactionAccuracy * 100);
        }
      }

      return {
        intensity: Math.round(rms * 100),
        volume: Math.round(peak * 100),
        reactionAccuracy
      };
    } catch (err) {
      console.error('Error analyzing audio:', err);
      return {
        intensity: 0,
        volume: 0,
        reactionAccuracy: 0
      };
    }
  }

/**
 * Fetches and displays the daily quote
 */
function fetchDailyQuote() {
    const quoteContainer = document.getElementById('dailyQuoteContainer');
    if (!quoteContainer) {
      console.log('Daily quote container not found');
      // Create it dynamically if not found, or just return
      const main = document.querySelector('main');
      if (main) {
        const container = document.createElement('div');
        container.id = 'dailyQuoteContainer';
        container.className = 'daily-quote-container';
        // Insert after header or somewhere visible. Let's put it before transfer points.
        const panels = document.querySelector('.panels');
        if (panels) {
          panels.parentNode.insertBefore(container, panels);
        }
      }
      return;
    }

    fetch(`${basePath}/api/v1/quote`)
      .then(response => response.json())
      .then(data => {
        if (data.content) {
          quoteContainer.innerHTML = `
          <div class="quote-content">"${data.content}"</div>
          <div class="quote-author">— ${data.author}</div>
        `;
          quoteContainer.style.display = 'flex';
        }
      })
      .catch(err => console.error('Failed to fetch quote:', err));
  }

  /**
   * Sends microphone results to the server
   */
  /**
   * Updates audience metrics (intensity & volume) in the Room view.
   */
  function updateAudienceMetrics({ intensity, volume }) {
    const intensityEl = document.getElementById('audienceIntensity');
    const volumeEl = document.getElementById('audienceVolume');

    if (intensityEl && typeof intensity !== 'undefined') {
      intensityEl.textContent = intensity;
      const container = intensityEl.closest('.audience-metric');
      if (container) {
        container.classList.add('metric-updated');
        setTimeout(() => container.classList.remove('metric-updated'), 800);
      }

    }

    if (volumeEl && typeof volume !== 'undefined') {
      volumeEl.textContent = volume;
      const container = volumeEl.closest('.audience-metric');
      if (container) {
        container.classList.add('metric-updated');
        setTimeout(() => container.classList.remove('metric-updated'), 800);
      }
    }
  }

  /**
   * Sends microphone results to the server (flattened payload expected by API)
   */
  function sendMicResults({ commandId, intensity, volume, reactionAccuracy }) {
    fetch(`${basePath}/api/v1/mic-results`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({

        userId: userId,
        commandId: currentCommandId,
        intensity: intensity,
        volume: volume,
        reactionAccuracy: reactionAccuracy
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("Mic results sent successfully:", data);
      })
      .catch((error) => {
        console.error("Error sending mic results:", error);
      });
  }

  // Initialize everything when DOM is loaded
  document.addEventListener('DOMContentLoaded', function () {
    // Fetch daily quote
    fetchDailyQuote();

    // Transfer Points AJAX handler
    const transferForm = document.querySelector('form[action*="transfer_points.php"]');
    if (transferForm) {
      transferForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const recipient = transferForm.querySelector('[name="recipient"]').value.trim();
        const amount = parseInt(transferForm.querySelector('[name="amount"]').value, 10);
        const message = transferForm.querySelector('[name="message"]').value.trim();
        const feedback = transferForm.querySelector('.form-feedback');
        feedback.textContent = '';
        if (!recipient || !amount || amount <= 0) {
          feedback.textContent = 'Please enter a valid recipient and amount.';
          feedback.style.color = '#c92a2a';
          return;
        }
        transferForm.querySelector('button[type="submit"]').disabled = true;
        fetch(basePath + '/api/v1/points/transfer', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            fromUserId: window.userId,
            toUsername: recipient,
            amount: amount,
            message: message
          })
        })
          .then(r => {
            console.log('Response status:', r.status);
            console.log('Response headers:', r.headers);
            return r.text().then(text => {
              console.log('Response text:', text);
              if (text.trim() === '') {
                throw new Error('Empty response from server');
              }
              try {
                return JSON.parse(text);
              } catch (e) {
                throw new Error('Invalid JSON response: ' + text);
              }
            });
          })
          .then(resp => {
            transferForm.querySelector('button[type="submit"]').disabled = false;
            if (resp.success) {
              // Update points in UI
              const pointsLi = Array.from(document.querySelectorAll('.user-info-list li')).find(li => li.textContent.includes('Points:'));
              if (pointsLi) {
                pointsLi.innerHTML = `<strong>Points:</strong> ${resp.points}`;
              }
              feedback.textContent = 'Points transferred successfully!';
              feedback.style.color = '#2b8a3e';
              transferForm.reset();
            } else {
              feedback.textContent = resp.error || 'Transfer failed.';
              feedback.style.color = '#c92a2a';
            }
          })
          .catch(e => {
            transferForm.querySelector('button[type="submit"]').disabled = false;
            feedback.textContent = 'Transfer failed: ' + e;
            feedback.style.color = '#c92a2a';
          });
      });
    });
}

/**
 * Add CSS for cue controls
 */
function addCueControlsStyles() {
  const styleId = 'cueControlsStyle';

  // Don't add styles if they already exist
  if (document.getElementById(styleId)) return;

  const cueControlsStyle = document.createElement('style');
  cueControlsStyle.id = styleId;
  cueControlsStyle.innerHTML = `
    .cue-controls-fixed {
      position: absolute;
      left: 80px;
      top: -20px;
      z-index: 10001;
      display: flex;
      gap: 0.5em;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      padding: 6px 10px;
    }
    .cue-btn {
      border-radius: 6px;
      border: 2px solid #ccc;
      background: #f8f9fa;
      cursor: pointer;
      transition: background 0.2s, border 0.2s;
      outline: none;
      padding: 4px 8px;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .cue-btn:active {
      filter: brightness(0.65);
    }
  `;
  document.head.appendChild(cueControlsStyle);
}

/**
 * Cleanup resources on page unload
 */
function cleanup() {
  if (evtSource) {
    evtSource.close();
    evtSource = null;
  }

  clearTimeout(reconnectTimer);
  clearCommandTimeouts();
  cleanupMicRecording();
}

/**
 * Initialize everything when the DOM is loaded
 */
async function checkSimAudience() {
  try {
    const resp = await fetch(`${basePath}/api/v1/admin/simulated-audience`, { method: 'GET' });
    const json = await resp.json();
    if (json && json.success) {
      simAudienceEnabled = json.enabled === true || json.enabled === '1';
    }
  } catch (e) {
    console.error('Failed to check simulated audience setting:', e);
  }
}

/**
 * Plays simulated audience sounds based on command type, intensity (0-100) and duration
 */
function playSimulatedAudience(commandType, intensity = 50, durationSec = 5) {
  stopSimulatedAudio(); // Make sure nothing else is playing

  const audioBase = `${basePath}/audio/`;
  const soundMap = {
    applause: ['clap1.mp3', 'clap2.mp3', 'clap3.mp3', 'clap4.mp3'],
    clap: ['clap1.mp3', 'clap2.mp3', 'clap3.mp3', 'clap4.mp3'],
    cheer: ['cheer1.mp3', 'cheer2.mp3', 'cheer3.mp3', 'cheer_whistle1.mp3', 'cheer_whistle2.mp3'],
    boo: ['boo1.mp3', 'boo2.mp3', 'boo3.mp3', 'boo4.mp3'],
    murmur: ['murmur1.mp3', 'murmur2.mp3', 'murmur3.mp3', 'murmur4.mp3'],
    stomp: ['stomping1.mp3', 'stomping2.mp3', 'stomping3.mp3', 'stopming4.mp3']
  };

  const clips = soundMap[commandType] || soundMap['applause'];
  if (!clips || clips.length === 0) return;

  // Determine how many clips to layer (1-8) based on intensity
  const layers = Math.max(1, Math.min(8, Math.round(intensity / 15)));

  for (let i = 0; i < layers; i++) {
    const file = clips[Math.floor(Math.random() * clips.length)];
    const audio = new Audio(audioBase + file);
    // Volume between 0.3-1 depending on intensity plus slight randomness
    const volume = Math.min(1, 0.3 + (intensity / 100) * 0.7 + (Math.random() * 0.1 - 0.05));
    audio.volume = volume;
    audio.play().catch(console.error);
    currentSimAudio.push(audio);
  }

  // Stop and clear after duration
  setTimeout(stopSimulatedAudio, durationSec * 1000);
}

function stopSimulatedAudio() {
  currentSimAudio.forEach(a => {
    try {
      a.pause();
      a.currentTime = 0;
    } catch (e) { }
  });
  currentSimAudio = [];
}

document.addEventListener('DOMContentLoaded', async function () {
  // Check sim audience first
  await checkSimAudience();
  // Add styles
  addCueControlsStyles();

  // Initialize components
  initCategoriesModal();
  setupCueControls();
  // setupTransferPointsForm(); // Logic is inline in previous DOMContentLoaded handler

  // Check if simulated audience is enabled then connect SSE
  connectSSE();

  // Set up mic recording indicator
  micIndicator = document.getElementById('micRecordingIndicator');
});

// Clean up resources when page is unloaded
window.addEventListener("beforeunload", cleanup);
