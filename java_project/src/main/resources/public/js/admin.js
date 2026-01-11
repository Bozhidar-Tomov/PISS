// Get the base path from a global variable that will be set in the view
const basePath = window.basePath || "";
const intensitySlider = document.getElementById('intensity');
const intensityValue = document.getElementById('intensityValue');

function updateSlider(slider) {
    if (!slider) return;
    const value = slider.value;
    const max = slider.max || 100;
    const percentage = (value / max) * 100;
    slider.style.setProperty('--range-progress', `${percentage}%`);
}

if (intensitySlider) {
  updateSlider(intensitySlider);
  intensitySlider.addEventListener('input', function(e) {
    intensityValue.textContent = e.target.value;
    updateSlider(e.target);
  });
}

// Add form submission handler
const commandForm = document.getElementById('commandForm');
if (commandForm) {
  commandForm.addEventListener('submit', function(e) {
      const submitButton = this.querySelector('button[type="submit"]');
      const feedbackElement = document.getElementById('commandFormFeedback');
      
      feedbackElement.textContent = 'Sending command...';
      feedbackElement.style.color = '#2b8a3e';
      
      submitButton.disabled = true;
      submitButton.textContent = 'Sending...';
  });
}

// Function to update statistics section
function updateStatistics() {
  fetch(basePath + '/api/v1/admin/stats')
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        console.error('Error fetching statistics:', data.error);
        return;
      }
      
      // Update elements with data
      updateElement('activeUsers', data.activeUsers);
      updateElement('currentVolume', data.currentVolume);
      updateElement('responseRate', data.responseRate);
      
      // Update SSE status
      const statusDotElement = document.getElementById('statusDot');
      const statusTextElement = document.getElementById('statusText');
      if (statusDotElement && statusTextElement) {
        statusDotElement.className = `status-dot ${data.sseStatus}`;
        statusTextElement.textContent = data.statusText;
      }
      
      // Update last command info if available
      updateLastCommandInfo(data.lastCommand);
    })
    .catch(error => {
      console.error('Failed to fetch statistics:', error);
    });
}

// Helper function to update element text content
function updateElement(id, value) {
  const element = document.getElementById(id);
  if (element) {
    element.textContent = value;
  }
}

// Update last command information
function updateLastCommandInfo(cmd) {
  const lastCommandInfoElement = document.getElementById('lastCommandInfo');
  if (!lastCommandInfoElement || !cmd) return;
  
  const html = `
    <strong>Last Command:</strong><br>
    <b>Type:</b> ${cmd.type}<br>
    <b>Intensity:</b> ${cmd.intensity}<br>
    <b>Duration:</b> ${cmd.duration}s<br>
    <b>Countdown:</b> ${cmd.countdown}s<br>
    <b>Target Categories:</b> ${Array.isArray(cmd.targetCategories) ? cmd.targetCategories.join(', ') : (cmd.targetCategories || 'All')}<br>
    <b>Target Gender:</b> ${cmd.targetGender || 'All'}<br>
    <b>Message:</b> ${cmd.message || ''}<br>
    <b>Sent at:</b> ${new Date(cmd.timestamp * 1000).toLocaleString()}
  `;
  lastCommandInfoElement.innerHTML = html;
}

// Update simulated audience button and slider
function updateSimAudienceBtnAndSlider() {
  const simBtn = document.getElementById('simAudienceBtn');
  const simBtnText = document.getElementById('simAudienceBtnText');
  const sliderWrapper = document.getElementById('intensitySliderWrapper');
  
  if (!simBtn || !simBtnText) return;
  
  fetch(basePath + '/api/v1/admin/simulated-audience', { method: 'GET' })
    .then(r => r.json())
    .then(resp => {
      if (resp.success) {
        simBtnText.textContent = resp.enabled ? 'Disable' : 'Enable';
        if (sliderWrapper) sliderWrapper.style.display = resp.enabled ? '' : 'none';
      }
    })
    .catch(error => {
      console.error('Failed to check simulated audience status:', error);
    });
}

// Handle simulated audience toggle
function setupSimAudienceToggle() {
  const simBtn = document.getElementById('simAudienceBtn');
  const simBtnText = document.getElementById('simAudienceBtnText');
  const simFeedback = document.getElementById('simAudienceFeedback');
  
  if (!simBtn || !simBtnText || !simFeedback) return;
  
  simBtn.addEventListener('click', function () {
    simFeedback.textContent = '';
    const enable = simBtnText.textContent.trim().toLowerCase() === 'enable';
    
    fetch(basePath + '/api/v1/admin/simulated-audience', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: enable ? 'sim_audience=on' : ''
    })
      .then(r => r.json())
      .then(resp => {
        if (resp.success) {
          updateSimAudienceBtnAndSlider();
          simFeedback.textContent = 'Simulated Audience ' + (enable ? 'enabled!' : 'disabled!');
          simFeedback.style.color = '#2b8a3e';
        } else {
          simFeedback.textContent = resp.error || 'Failed to update.';
          simFeedback.style.color = '#c92a2a';
        }
      })
      .catch(e => {
        simFeedback.textContent = 'Failed: ' + e;
        simFeedback.style.color = '#c92a2a';
      });
  });
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
  updateSimAudienceBtnAndSlider();
  setupSimAudienceToggle();
  
  // Start periodic updates of statistics
  updateStatistics(); // Initial update
  setInterval(updateStatistics, 3000); // Update every 3 seconds
});