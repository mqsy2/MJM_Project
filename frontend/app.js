// ============================================
// Curtain Call — Dashboard JavaScript
// ============================================

const API_BASE = 'http://localhost/curtain_call/api';
let pollingInterval = null;

// ---- Initialize ----
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    loadSettings();
    refreshSensorData();
    refreshLogs();
    startPolling();

    // Position slider — sends target position in real-time
    let sliderTimeout = null;
    document.getElementById('position-slider').addEventListener('input', (e) => {
        const pos = parseInt(e.target.value);
        document.getElementById('position-value').textContent = pos + '%';
        updateCurtainStatusByPosition(pos);

        // Debounce: wait 300ms after user stops dragging before sending
        clearTimeout(sliderTimeout);
        sliderTimeout = setTimeout(() => {
            sendPosition(pos);
        }, 300);
    });

    // Settings slider live updates
    document.getElementById('setting-light-high').addEventListener('input', (e) => {
        document.getElementById('setting-light-high-val').textContent = e.target.value;
    });
    document.getElementById('setting-light-low').addEventListener('input', (e) => {
        document.getElementById('setting-light-low-val').textContent = e.target.value;
    });
    document.getElementById('setting-temp-high').addEventListener('input', (e) => {
        document.getElementById('setting-temp-high-val').textContent = e.target.value;
    });
});

// ---- Polling ----
function startPolling() {
    pollingInterval = setInterval(() => {
        refreshSensorData();
    }, 3000); // Every 3 seconds
}

// ---- Sensor Data ----
async function refreshSensorData() {
    try {
        const res = await fetch(`${API_BASE}/sensor_data.php?limit=1`);
        const data = await res.json();

        if (data && data.temperature !== undefined) {
            updateSensorDisplay('sensor-temp', parseFloat(data.temperature).toFixed(1));
            updateSensorDisplay('sensor-humidity', parseFloat(data.humidity).toFixed(1));
            updateSensorDisplay('sensor-light', data.light_level);
        }
    } catch (err) {
        console.log('Sensor polling - waiting for data:', err.message);
    }
}

function updateSensorDisplay(elementId, value) {
    const el = document.getElementById(elementId);
    if (el.textContent !== String(value)) {
        el.textContent = value;
        el.classList.add('sensor-update');
        setTimeout(() => el.classList.remove('sensor-update'), 500);
    }
}


async function sendPosition(targetPosition) {
    try {
        const res = await fetch(`${API_BASE}/command.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: targetPosition > 50 ? 'OPEN' : (targetPosition === 0 ? 'CLOSE' : 'OPEN'),
                target_position: targetPosition
            })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('last-action-text').textContent =
                `Moving to ${targetPosition}% — just now`;
        }
    } catch (err) {
        console.error('Position command error:', err);
    }
}

// ---- AI Command ----
async function sendAICommand() {
    const input = document.getElementById('ai-input').value.trim();
    if (!input) return;

    const btn = document.getElementById('btn-ai-send');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Thinking...';
    btn.disabled = true;

    // Reset UI
    const responseDiv = document.getElementById('ai-response');
    const responseText = document.getElementById('ai-response-text');
    const errorDiv = document.getElementById('ai-error');
    const errorText = document.getElementById('ai-error-text');

    responseDiv.classList.add('hidden');
    errorDiv.classList.add('hidden');

    try {
        const res = await fetch(`${API_BASE}/ai_process.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_input: input })
        });

        const data = await res.json();

        if (!res.ok) {
            const errData = await res.json().catch(() => null);
            if (res.status === 429 || (errData?.error?.code === 429)) {
                throw new Error("AI is busy (Rate Limit). Please wait 1 minute.");
            }
            throw new Error(errData?.error || `Server error (${res.status})`);
        }

        if (!data.success) {
            throw new Error(data.error || 'AI request failed');
        }

        const decision = data.ai_response;
        if (!decision || typeof decision.position === 'undefined') {
            throw new Error("Invalid response from AI");
        }

        const pos = decision.position;

        // Move the slider to AI's chosen position
        document.getElementById('position-slider').value = pos;
        document.getElementById('position-value').textContent = pos + '%';
        updateCurtainStatusByPosition(pos);

        // Send the position command to the motor
        sendPosition(pos);

        // Show Success
        responseDiv.classList.remove('hidden');
        responseText.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <span class="badge badge-ai">AI Decision</span>
                <span class="text-white font-semibold">${pos}%</span>
            </div>
            <p class="text-white/50 text-xs">${decision.reason}</p>
        `;
        document.getElementById('last-action-text').textContent =
            `AI: ${decision.reason}`;
        refreshLogs();

    } catch (err) {
        console.error('AI error:', err);
        // Show Error Widget
        errorDiv.classList.remove('hidden');
        errorText.textContent = err.message || 'Connection error. Is XAMPP running?';
        lucide.createIcons();
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
        lucide.createIcons();
    }
}

// ---- Curtain Status by Position ----
function updateCurtainStatusByPosition(pos) {
    const statusText = document.getElementById('curtain-status-text');
    const visual = document.querySelector('.curtain-visual');

    visual.classList.remove('curtain-open', 'curtain-closed');

    if (pos === 0) {
        statusText.textContent = 'CLOSED';
        visual.classList.add('curtain-closed');
    } else if (pos <= 20) {
        statusText.textContent = 'ALMOST CLOSED';
    } else if (pos <= 40) {
        statusText.textContent = 'HALF CLOSED';
    } else if (pos <= 60) {
        statusText.textContent = 'HALF OPEN';
    } else if (pos <= 80) {
        statusText.textContent = 'ALMOST OPEN';
    } else if (pos < 100) {
        statusText.textContent = 'ALMOST OPEN';
    } else {
        statusText.textContent = 'OPEN';
        visual.classList.add('curtain-open');
    }
}

// ---- Activity Logs ----
async function refreshLogs() {
    try {
        const res = await fetch(`${API_BASE}/logs.php?limit=15`);
        const data = await res.json();

        const logContainer = document.getElementById('activity-log');

        if (data.logs && data.logs.length > 0) {
            logContainer.innerHTML = data.logs.map(log => {
                const badgeClass = log.source === 'AI' ? 'badge-ai' :
                    log.source === 'AUTO' ? 'badge-auto' : 'badge-manual';
                const icon = log.action === 'OPEN' ? 'chevrons-left' :
                    log.action === 'CLOSE' ? 'chevrons-right' : 'pause';
                const timeAgo = getTimeAgo(log.logged_at);

                return `
                    <div class="log-item">
                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i data-lucide="${icon}" class="w-4 h-4 text-white/40"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-white/80">${log.action}</span>
                                <span class="badge ${badgeClass}">${log.source}</span>
                                <span class="text-xs text-white/20 ml-auto flex-shrink-0">${timeAgo}</span>
                            </div>
                            ${log.reason ? `<p class="text-xs text-white/30 truncate">${log.reason}</p>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
            lucide.createIcons();
        } else {
            logContainer.innerHTML = '<div class="text-sm text-white/30 text-center py-8">No activity yet</div>';
        }
    } catch (err) {
        console.log('Logs fetch error:', err.message);
    }
}

// ---- Settings ----
async function loadSettings() {
    try {
        const res = await fetch(`${API_BASE}/settings.php`);
        const settings = await res.json();

        if (settings.auto_mode) {
            document.getElementById('setting-auto-mode').checked = settings.auto_mode.value === '1';
        }
        if (settings.light_threshold_high) {
            const val = settings.light_threshold_high.value;
            document.getElementById('setting-light-high').value = val;
            document.getElementById('setting-light-high-val').textContent = val;
        }
        if (settings.light_threshold_low) {
            const val = settings.light_threshold_low.value;
            document.getElementById('setting-light-low').value = val;
            document.getElementById('setting-light-low-val').textContent = val;
        }
        if (settings.temp_threshold_high) {
            const val = settings.temp_threshold_high.value;
            document.getElementById('setting-temp-high').value = val;
            document.getElementById('setting-temp-high-val').textContent = val;
        }
        if (settings.curtain_status) {
            const statusVal = settings.curtain_status.value;
            const pos = statusVal === 'OPEN' ? 100 : (statusVal === 'CLOSED' ? 0 : 50);
            document.getElementById('position-slider').value = pos;
            document.getElementById('position-value').textContent = pos + '%';
            updateCurtainStatusByPosition(pos);
        }
    } catch (err) {
        console.log('Settings fetch error:', err.message);
    }
}

async function updateSetting(key, value) {
    try {
        await fetch(`${API_BASE}/settings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key, value })
        });
    } catch (err) {
        console.error('Settings update error:', err);
    }
}

function toggleSettings() {
    const panel = document.getElementById('settings-panel');
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
        panel.scrollIntoView({ behavior: 'smooth' });
    }
}

// ---- Utilities ----
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);

    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    return date.toLocaleDateString();
}
