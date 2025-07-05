        document.addEventListener('DOMContentLoaded', function() {
            const visibilityRadios = document.querySelectorAll('input[name="visibility_type"]');
            const teamSelection = document.getElementById('team-selection');
            const personSelection = document.getElementById('person-selection');
            const selectAllTeamsBtn = document.getElementById('select-all-teams');
            const deselectAllTeamsBtn = document.getElementById('deselect-all-teams');
            const selectAllPersonsBtn = document.getElementById('select-all-persons');
            const deselectAllPersonsBtn = document.getElementById('deselect-all-persons');
            const teamCheckboxes = document.querySelectorAll('input[name="team_ids[]"]');
            const personCheckboxes = document.querySelectorAll('input[name="person_ids[]"]');
            const personSearch = document.getElementById('person-search');
            const personItems = document.querySelectorAll('.person-item');

            function toggleVisibility() {
                const selectedVisibility = document.querySelector('input[name="visibility_type"]:checked').value;
                
                // Hide all selectors
                teamSelection.style.display = 'none';
                personSelection.style.display = 'none';
                
                // Show relevant selector
                if (selectedVisibility === 'teams') {
                    teamSelection.style.display = 'block';
                } else if (selectedVisibility === 'persons') {
                    personSelection.style.display = 'block';
                }
            }

            // Handle visibility radio changes
            visibilityRadios.forEach(radio => {
                radio.addEventListener('change', toggleVisibility);
            });

            // Team selection handlers
            if (selectAllTeamsBtn) {
                selectAllTeamsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = true);
                });
            }

            if (deselectAllTeamsBtn) {
                deselectAllTeamsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = false);
                });
            }

            // Person selection handlers
            if (selectAllPersonsBtn) {
                selectAllPersonsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    personCheckboxes.forEach(checkbox => checkbox.checked = true);
                });
            }

            if (deselectAllPersonsBtn) {
                deselectAllPersonsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    personCheckboxes.forEach(checkbox => checkbox.checked = false);
                });
            }

            // Person search functionality
            if (personSearch) {
                personSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    personItems.forEach(item => {
                        const name = item.dataset.name;
                        const username = item.dataset.username;
                        
                        if (name.includes(searchTerm) || username.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Initialize visibility
            toggleVisibility();
        });
