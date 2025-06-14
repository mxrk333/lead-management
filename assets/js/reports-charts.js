// Chart color configuration
const chartColors = {
    primary: '#4361ee',
    success: '#10b981', 
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6',
    gray: '#6b7280'
};

// Initialize all charts when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Status Distribution Chart
    if (document.getElementById('statusChart')) {
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = JSON.parse(document.getElementById('statusData').value);
        
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: statusData.map(item => item.status),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        chartColors.primary,
                        chartColors.success,
                        chartColors.warning,
                        chartColors.danger,
                        chartColors.info
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }

    // Temperature Distribution Chart
    if (document.getElementById('temperatureChart')) {
        const tempCtx = document.getElementById('temperatureChart').getContext('2d');
        const tempData = JSON.parse(document.getElementById('temperatureData').value);
        
        new Chart(tempCtx, {
            type: 'doughnut',
            data: {
                labels: tempData.map(item => item.temperature),
                datasets: [{
                    data: tempData.map(item => item.count),
                    backgroundColor: [
                        chartColors.danger,
                        chartColors.warning,
                        chartColors.info
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }

    // Projects Chart
    if (document.getElementById('projectsChart')) {
        const projectsCtx = document.getElementById('projectsChart').getContext('2d');
        const projectsData = JSON.parse(document.getElementById('projectsData').value);
        
        new Chart(projectsCtx, {
            type: 'bar',
            data: {
                labels: projectsData.map(item => item.developer),
                datasets: [{
                    label: 'Number of Leads',
                    data: projectsData.map(item => item.count),
                    backgroundColor: chartColors.primary,
                    borderColor: chartColors.primary,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Models Chart
    if (document.getElementById('modelsChart')) {
        const modelsCtx = document.getElementById('modelsChart').getContext('2d');
        const modelsData = JSON.parse(document.getElementById('modelsData').value);
        
        new Chart(modelsCtx, {
            type: 'bar',
            data: {
                labels: modelsData.map(item => item.project_model),
                datasets: [{
                    label: 'Number of Leads',
                    data: modelsData.map(item => item.count),
                    backgroundColor: chartColors.success,
                    borderColor: chartColors.success,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Sources Chart
    if (document.getElementById('sourcesChart')) {
        const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
        const sourcesData = JSON.parse(document.getElementById('sourcesData').value);
        
        new Chart(sourcesCtx, {
            type: 'bar',
            data: {
                labels: sourcesData.map(item => item.source),
                datasets: [{
                    label: 'Number of Leads',
                    data: sourcesData.map(item => item.count),
                    backgroundColor: chartColors.info,
                    borderColor: chartColors.info,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Team Performance Chart
    let teamChart = null;
    
    window.updateTeamChart = function(view) {
        const ctx = document.getElementById('teamPerformanceChart').getContext('2d');
        const performanceData = JSON.parse(document.getElementById('teamPerformanceData').value || '[]');
        
        // Destroy existing chart if it exists
        if (teamChart) {
            teamChart.destroy();
        }
        
        // Update active button state
        document.querySelectorAll('.chart-actions .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`.chart-actions .btn[onclick*="${view}"]`).classList.add('active');
        
        let data, label;
        switch(view) {
            case 'leads':
                data = performanceData.map(item => parseInt(item.total_leads) || 0);
                label = 'Total Leads';
                break;
            case 'conversion':
                data = performanceData.map(item => parseFloat(item.conversion_rate) || 0);
                label = 'Conversion Rate (%)';
                break;
            case 'value':
                data = performanceData.map(item => parseFloat(item.total_value) || 0);
                label = 'Total Value (₱)';
                break;
        }
        
        teamChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: performanceData.map(item => item.name),
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: chartColors.primary,
                    borderColor: chartColors.primary,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (view === 'value') {
                                    return '₱' + value.toLocaleString();
                                } else if (view === 'conversion') {
                                    return value + '%';
                                }
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (view === 'value') {
                                    return '₱' + value.toLocaleString();
                                } else if (view === 'conversion') {
                                    return value + '%';
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        });
    };
    
    // Initialize team performance chart with leads view
    if (document.getElementById('teamPerformanceChart')) {
        updateTeamChart('leads');
    }
}); 