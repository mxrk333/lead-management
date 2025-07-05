        function toggleAcknowledgeButton() {
            const checkbox = document.getElementById('acknowledge-checkbox-details');
            const button = document.getElementById('acknowledge-btn-details');
            
            if (checkbox.checked) {
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
            } else {
                button.disabled = true;
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
            }
        }
