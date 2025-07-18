<?php
session_start();
require_once 'config/database.php';

// Simple test without functions.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Recruitment Test</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Simple Recruitment Test</h2>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Add Test Lead</h3>
                <form id="testForm">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="full_name" value="Test User" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" class="form-control" name="contact_number" value="09123456789" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="test@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interest Level</label>
                        <select class="form-control" name="interest_level" required>
                            <option value="Hot">Hot</option>
                            <option value="Warm">Warm</option>
                            <option value="Cold">Cold</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" required>
                            <option value="Inquiry">Inquiry</option>
                            <option value="Accreditation">Accreditation</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <select class="form-control" name="source" required>
                            <option value="Facebook Ads">Facebook Ads</option>
                            <option value="TikTok ads">TikTok ads</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="testSave()">Test Save</button>
                </form>
            </div>
            
            <div class="col-md-6">
                <h3>Results</h3>
                <div id="results"></div>
                
                <h3>Current Data</h3>
                <button class="btn btn-info" onclick="loadData()">Load Data</button>
                <div id="dataResults"></div>
            </div>
        </div>
    </div>

    <script>
        function testSave() {
            const form = document.getElementById('testForm');
            const formData = new FormData(form);
            formData.append('action', 'add_recruitment_lead');
            
            fetch('includes/functions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('results').innerHTML = '<pre>' + data + '</pre>';
            })
            .catch(error => {
                document.getElementById('results').innerHTML = 'Error: ' + error;
            });
        }
        
        function loadData() {
            const formData = new FormData();
            formData.append('action', 'get_recruitment_leads');
            
            fetch('includes/functions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('dataResults').innerHTML = '<pre>' + data + '</pre>';
            })
            .catch(error => {
                document.getElementById('dataResults').innerHTML = 'Error: ' + error;
            });
        }
    </script>
</body>
</html>
