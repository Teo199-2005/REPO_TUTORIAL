// Global Modal System for CSCS Tap n Track
function customConfirm(message, title = 'Confirm Action', type = 'question') {
    return new Promise((resolve) => {
        // Decode HTML entities and format message
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = message;
        const decodedMessage = tempDiv.textContent || tempDiv.innerText;
        
        // Split by newlines and format as list items
        const lines = decodedMessage.split('\n').filter(line => line.trim());
        const mainQuestion = lines[0];
        const details = lines.slice(1);
        
        let formattedMessage = `<p class="custom-modal-question">${mainQuestion}</p>`;
        if (details.length > 0) {
            formattedMessage += '<ul class="custom-modal-list">';
            details.forEach(line => {
                const cleanLine = line.trim().replace(/^[•\-]\s*/, '');
                if (cleanLine) {
                    formattedMessage += `<li>${cleanLine}</li>`;
                }
            });
            formattedMessage += '</ul>';
        }
        
        const modalHTML = `
            <div class="custom-modal-overlay" id="customModal">
                <div class="custom-modal-container">
                    <div class="custom-modal-header">
                        <h3 class="custom-modal-title">${title}</h3>
                    </div>
                    <div class="custom-modal-body">
                        ${formattedMessage}
                    </div>
                    <div class="custom-modal-footer">
                        <button class="custom-modal-btn custom-modal-btn-cancel" onclick="closeCustomModal(false)">Cancel</button>
                        <button class="custom-modal-btn custom-modal-btn-confirm" onclick="closeCustomModal(true)">Confirm</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Mascot decoration (see public/js/mascot.js). The dialog picks its own
        // pose from the `type` argument, so a confirmation reads as "thinking"
        // and an error reads as "worried".
        const dialog = document.querySelector('#customModal .custom-modal-container');
        if (dialog && typeof window.__mascotDecorateDialog === 'function') {
            window.__mascotDecorateDialog(dialog, type);
        }

        window.closeCustomModal = function(result) {
            const modal = document.getElementById('customModal');
            if (modal) {
                modal.remove();
            }
            resolve(result);
        };
    });
}

function customAlert(message, title = 'Alert', type = 'info') {
    return new Promise((resolve) => {
        // Decode HTML entities and format message
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = message;
        const decodedMessage = tempDiv.textContent || tempDiv.innerText;
        
        // Split by newlines and format
        const lines = decodedMessage.split('\n').filter(line => line.trim());
        const mainMessage = lines[0];
        const details = lines.slice(1);
        
        let formattedMessage = `<p class="custom-modal-question">${mainMessage}</p>`;
        if (details.length > 0) {
            formattedMessage += '<ul class="custom-modal-list">';
            details.forEach(line => {
                const cleanLine = line.trim().replace(/^[•\-]\s*/, '');
                if (cleanLine) {
                    formattedMessage += `<li>${cleanLine}</li>`;
                }
            });
            formattedMessage += '</ul>';
        }
        
        const modalHTML = `
            <div class="custom-modal-overlay" id="customModal">
                <div class="custom-modal-container">
                    <div class="custom-modal-header">
                        <h3 class="custom-modal-title">${title}</h3>
                    </div>
                    <div class="custom-modal-body">
                        ${formattedMessage}
                    </div>
                    <div class="custom-modal-footer">
                        <button class="custom-modal-btn custom-modal-btn-confirm" onclick="closeCustomAlert()">OK</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Mascot decoration (see public/js/mascot.js).
        const dialog = document.querySelector('#customModal .custom-modal-container');
        if (dialog && typeof window.__mascotDecorateDialog === 'function') {
            window.__mascotDecorateDialog(dialog, type);
        }

        window.closeCustomAlert = function() {
            const modal = document.getElementById('customModal');
            if (modal) {
                modal.remove();
            }
            resolve(true);
        };
    });
}

// Override native alert only.
// NOTE: Do NOT override window.confirm here. A promise-based confirm() breaks
// every synchronous caller (if (confirm(msg)) {...}), because a Promise is
// always truthy — those callers would "auto-approve" without waiting for the
// user (this silently deleted announcements with a 0-second confirm).
// Async code that wants the styled dialog must call customConfirm() directly
// and await it (see announcements_create.php for the correct pattern).
window.alert = function(message) {
    customAlert(message);
};

