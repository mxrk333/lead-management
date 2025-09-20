// recruitment-dashboard.js
// JavaScript for recruitment dashboard functionality

console.log('recruitment-dashboard.js loaded successfully');

const currentSort = { column: "created_at", order: "DESC" }
let currentFilters = {}
const bootstrap = window.bootstrap // Declare the bootstrap variable

// Initialize dashboard
document.addEventListener("DOMContentLoaded", () => {
  // Load data without default filters
  loadRecruitmentData()
})

// Load statistics
function loadStats() {
  const formData = new FormData()
  formData.append("action", "get_recruitment_stats")

  fetch("includes/functions.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const stats = data.data

        // Update total leads
        document.getElementById("totalLeads").textContent = stats.total_leads
        document.getElementById("recentLeads").textContent = stats.recent_leads

        // Update interest level counts
        let hotCount = 0,
          warmCount = 0,
          coldCount = 0
        stats.by_interest_level.forEach((item) => {
          switch (item.interest_level) {
            case "Hot":
              hotCount = item.count
              break
            case "Warm":
              warmCount = item.count
              break
            case "Cold":
              coldCount = item.count
              break
          }
        })

        document.getElementById("hotLeads").textContent = hotCount
        document.getElementById("warmLeads").textContent = warmCount
      }
    })
    .catch((error) => {
      console.error("Error loading stats:", error)
    })
}

// Load recruitment data

function loadRecruitmentData() {
    const formData = new FormData();
    formData.append('filters', JSON.stringify(currentFilters));
    
    fetch('recruitment-filter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Received data:', data);
            displayRecruitmentLeads(data.data);
        } else {
            console.error('Error:', data.message);
            showNotification('Error loading data', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error loading data', 'error');
    });
}

// Display recruitment data in cards
window.displayRecruitmentData = function displayRecruitmentData(leads) {
  // Get current user info from global variables
  const CURRENT_USER_ROLE = window.CURRENT_USER_ROLE || 'user';
  const CURRENT_USER_ID = window.CURRENT_USER_ID || null;

  // Only show all leads for admin, else filter by recruiter_id
  let visibleLeads = leads;
  if (CURRENT_USER_ROLE === 'manager') {
      visibleLeads = leads.filter(lead => lead.recruiter_id == CURRENT_USER_ID);
  }

  // Remove client-side filtering since server-side filtering should handle this
  // The server should already return filtered results based on onboardStatus

  // Sort by activity status: Active first, then Inactive
  visibleLeads.sort((a, b) => {
    // Primary sort: Active status (Active first)
    if (a.status === 'Active' && b.status !== 'Active') return -1;
    if (a.status !== 'Active' && b.status === 'Active') return 1;
    
    // Secondary sort: by creation date (newest first)
    return new Date(b.created_at) - new Date(a.created_at);
  });

  const cardsContainer = document.getElementById("recruitmentTableBody");
  cardsContainer.innerHTML = "";

  if (!visibleLeads || visibleLeads.length === 0) {
      cardsContainer.innerHTML = `
          <div class="col-span-full flex flex-col items-center justify-center py-12">
              <i class="fas fa-search text-4xl mb-4 text-gray-400"></i>
              <p class="text-lg font-medium text-gray-600 mb-2">No recruitment leads found</p>
              <small class="text-gray-500">Try adjusting your filters or search terms</small>
          </div>
      `;
      return;
  }

  visibleLeads.forEach((lead, index) => {
      const dateObj = new Date(lead.created_at);
      const timestamp = dateObj.toLocaleString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
          hour12: true
      }).replace(',', '').replace(/(\d{4}) (\d{1,2}):/, '$1 at $2:');

      // Masking logic: only recruiter or admin can see full details
      const isOwnLead = (CURRENT_USER_ID == lead.recruiter_id);
      const isAdmin = (CURRENT_USER_ROLE === 'admin');
      const canSeeDetails = isOwnLead || isAdmin;
      const canEditDelete = isOwnLead || isAdmin;

      function maskWord(word) {
          if (word.length <= 2) return word[0] + '*'.repeat(word.length - 1);
          if (word.length <= 4) return word[0] + '*'.repeat(word.length - 2) + word[word.length - 1];
          return word.slice(0, 2) + '*'.repeat(word.length - 4) + word.slice(-2);
      }

      function maskText(text) {
          if (!text) return '';
          return text.split(' ').map(maskWord).join(' ');
      }

      // Create card element
      const card = document.createElement("div");
      card.className = "bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-lg transition-all duration-300 p-6 relative overflow-hidden";
      card.style.animationDelay = `${index * 50}ms`;
      
      // Add gradient accent
      card.style.background = "linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)";
      
      card.innerHTML = `
          <!-- Status indicator bar -->
          <div class="absolute top-0 left-0 w-full h-1 ${lead.status === 'Active' ? 'bg-gradient-to-r from-green-400 to-emerald-500' : 'bg-gradient-to-r from-red-400 to-rose-500'}"></div>
          
          <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-3">
                  <div class="relative">
                      <div class="w-12 h-12 bg-gradient-to-br from-blue-500 via-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                          ${(lead.full_name || 'N').charAt(0).toUpperCase()}
                      </div>
                      <div class="absolute -bottom-1 -right-1 w-4 h-4 ${lead.status === 'Active' ? 'bg-green-500' : 'bg-red-500'} rounded-full border-2 border-white"></div>
                  </div>
                  <div>
                      <h3 class="font-bold text-gray-900 text-lg truncate max-w-[180px]" title="${lead.full_name}">
                          ${canSeeDetails ? (lead.full_name || 'N/A') : maskText(lead.full_name || '')}
                      </h3>
                      <div id="credentials-${lead.id}" class="hidden mt-1 p-2 bg-blue-50 rounded-lg border border-blue-200">
                          <div class="text-xs text-blue-700 font-medium">Login Credentials:</div>
                          <div class="text-xs text-blue-600 mt-1">
                              <div><strong>Username:</strong> <span class="font-mono bg-white px-1 rounded">${generateUsername(lead.full_name)}</span></div>
                              <div class="mt-1"><strong>Password:</strong> <span class="font-mono bg-white px-1 rounded">123456789innersparc</span></div>
                          </div>
                      </div>
                      <p class="text-xs text-gray-500 mt-1"><i class="fas fa-clock mr-1"></i>${timestamp || 'N/A'}</p>
                  </div>
              </div>
              <div class="flex flex-col items-end gap-2">
                  ${lead.status === 'Active' ? 
                      '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 border border-green-200"><i class="fas fa-circle text-green-500 mr-1 text-[6px]"></i>Active</span>' :
                      lead.status === 'Inactive' ? 
                      '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200"><i class="fas fa-circle text-red-500 mr-1 text-[6px]"></i>Inactive</span>' :
                      `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800 border border-gray-200">${lead.status || 'N/A'}</span>`
                  }
              </div>
          </div>
          
          <div class="grid grid-cols-1 gap-3 mb-5">
              <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                  <div class="flex items-center gap-3 text-sm">
                      <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full">
                          <i class="fas fa-phone text-blue-600 text-xs"></i>
                      </div>
                      <div class="flex-1">
                          <div class="text-xs text-gray-500 font-medium">Contact</div>
                          <div class="font-semibold text-gray-800 truncate">${canSeeDetails ? (lead.contact_number || 'No contact number') : maskText(lead.contact_number || '')}</div>
                      </div>
                  </div>
              </div>
              <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                  <div class="flex items-center gap-3 text-sm">
                      <div class="flex items-center justify-center w-8 h-8 bg-purple-100 rounded-full">
                          <i class="fas fa-envelope text-purple-600 text-xs"></i>
                      </div>
                      <div class="flex-1">
                          <div class="text-xs text-gray-500 font-medium">Email</div>
                          <div class="font-semibold text-gray-800 truncate">${canSeeDetails ? (lead.email || 'No email') : maskText(lead.email || '')}</div>
                      </div>
                  </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                  <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                      <div class="flex items-center gap-2 text-sm">
                          <div class="flex items-center justify-center w-6 h-6 bg-green-100 rounded-full">
                              <i class="fas fa-user-tie text-green-600 text-xs"></i>
                          </div>
                          <div class="flex-1">
                              <div class="text-xs text-gray-500 font-medium">Recruiter</div>
                              <div class="font-semibold text-gray-800 text-xs truncate">${lead.recruiter_name || 'N/A'}</div>
                          </div>
                      </div>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                      <div class="flex items-center gap-2 text-sm">
                          <div class="flex items-center justify-center w-6 h-6 bg-orange-100 rounded-full">
                              <i class="fas fa-users text-orange-600 text-xs"></i>
                          </div>
                          <div class="flex-1">
                              <div class="text-xs text-gray-500 font-medium">Team</div>
                              <div class="font-semibold text-gray-800 text-xs truncate">${lead.recruiter_team || 'No Team'}</div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl p-4 mb-5 border border-gray-100">
              <div class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                  <i class="fas fa-chart-line text-blue-500"></i>
                  Training Progress
              </div>
              <div class="space-y-4">
                  <div>
                      <div class="flex justify-between items-center mb-2">
                          <span class="text-xs font-bold text-blue-700 flex items-center gap-1">
                              <i class="fas fa-graduation-cap text-xs"></i>
                              Pre-recruitment
                          </span>
                          <span class="text-xs font-bold text-blue-700 bg-blue-100 px-2 py-1 rounded-full">${getPreRecruitmentPercent(lead)}%</span>
                      </div>
                      <div class="w-full bg-blue-100 rounded-full h-3 shadow-inner">
                          <div class="h-3 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full transition-all duration-500 shadow-sm" style="width: ${getPreRecruitmentPercent(lead)}%"></div>
                      </div>
                  </div>
                  
                  <div>
                      <div class="flex justify-between items-center mb-2">
                          <span class="text-xs font-bold text-orange-700 flex items-center gap-1">
                              <i class="fas fa-rocket text-xs"></i>
                              Post-recruitment
                          </span>
                          <span class="text-xs font-bold text-orange-700 bg-orange-100 px-2 py-1 rounded-full">${getPostRecruitmentPercent(lead)}%</span>
                      </div>
                      <div class="w-full bg-orange-100 rounded-full h-3 shadow-inner">
                          <div class="h-3 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full transition-all duration-500 shadow-sm" style="width: ${getPostRecruitmentPercent(lead)}%"></div>
                      </div>
                  </div>
                  
                  <div class="pt-2 border-t border-gray-200">
                      <div class="flex justify-between items-center mb-2">
                          <span class="text-sm font-bold text-green-700 flex items-center gap-1">
                              <i class="fas fa-trophy text-xs"></i>
                              Overall Progress
                          </span>
                          <span class="text-sm font-bold text-green-700 bg-green-100 px-3 py-1 rounded-full">${getProgressPercent(lead)}%</span>
                      </div>
                      <div class="w-full bg-green-100 rounded-full h-4 shadow-inner">
                          <div class="h-4 bg-gradient-to-r from-green-400 to-green-600 rounded-full transition-all duration-500 shadow-sm flex items-center justify-end pr-2" style="width: ${getProgressPercent(lead)}%">
                              ${getProgressPercent(lead) > 15 ? '<i class="fas fa-star text-white text-xs"></i>' : ''}
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-gray-200">
              <div class="flex items-center gap-2 text-xs text-gray-500 bg-gray-100 px-3 py-2 rounded-full">
                  <i class="fas fa-user-plus text-blue-500"></i> 
                  <span class="font-medium">${lead.source || 'N/A'}</span>
              </div>
              <div class="flex items-center gap-2">
                  ${canEditDelete ? `
                      ${(lead.LMS == 1 || lead.LMS === '1' || getPostRecruitmentPercent(lead) >= 75 || getProgressPercent(lead) >= 86) ? `
                          <button class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white p-2.5 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105" 
                                  onclick="toggleCredentials(${lead.id})" title="Show Login Credentials" id="onboardBtn-${lead.id}">
                              <i class="fas fa-rocket text-sm"></i>
                          </button>
                      ` : ''}
                      <button class="bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-white p-2.5 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105" 
                              onclick="editLead(${lead.id})" title="Edit">
                          <i class="fas fa-edit text-sm"></i>
                      </button>
                      <button class="bg-gradient-to-r from-red-400 to-pink-500 hover:from-red-500 hover:to-pink-600 text-white p-2.5 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105" 
                              onclick="deleteLead(${lead.id})" title="Delete">
                          <i class="fas fa-trash text-sm"></i>
                      </button>
                  ` : ''}
              </div>
          </div>
      `;
      
      cardsContainer.appendChild(card);
  })
}

// Generate username from full name
function generateUsername(fullName) {
  if (!fullName) return 'user.innersparc';
  
  // Remove special characters and convert to lowercase
  const cleanName = fullName.toLowerCase()
    .replace(/[^a-z\s]/g, '')
    .replace(/\s+/g, '')
    .trim();
  
  return cleanName + '.innersparc';
}

// Toggle credentials display
window.toggleCredentials = function toggleCredentials(leadId) {
  const credentialsDiv = document.getElementById(`credentials-${leadId}`);
  const button = document.getElementById(`onboardBtn-${leadId}`);
  
  if (credentialsDiv.classList.contains('hidden')) {
    credentialsDiv.classList.remove('hidden');
    credentialsDiv.classList.add('animate-fade-in');
    button.innerHTML = '<i class="fas fa-eye-slash text-sm"></i>';
    button.title = 'Hide Login Credentials';
  } else {
    credentialsDiv.classList.add('hidden');
    credentialsDiv.classList.remove('animate-fade-in');
    button.innerHTML = '<i class="fas fa-rocket text-sm"></i>';
    button.title = 'Show Login Credentials';
  }
}

// Helper functions for progress calculation
function getPreRecruitmentPercent(lead) {
    const keys = [
        'pre_assessment', 'accreditation', 'assessment', 'sales_training', 'site_tour', 'focus_projects'
    ];
    let checked = 0;
    keys.forEach(k => {
        if (lead[k]) checked++;
    });
    return Math.round((checked / keys.length) * 100);
}

function getPostRecruitmentPercent(lead) {
    const keys = [
        'habit_forming', 'digital_training', 'sales_training_materials', 'objection_handling', 'VAST',
        'sales_monitoring', 'LMS', 'comm_structure'
    ];
    let checked = 0;
    keys.forEach(k => {
        if (lead[k]) checked++;
    });
    return Math.round((checked / keys.length) * 100);
}

function getProgressPercent(lead) {
    const keys = [
        'pre_assessment', 'accreditation', 'assessment', 'sales_training', 'site_tour', 'focus_projects',
        'habit_forming', 'digital_training', 'sales_training_materials', 'objection_handling', 'VAST',
        'sales_monitoring', 'LMS', 'comm_structure'
    ];
    let checked = 0;
    keys.forEach(k => {
        if (lead[k]) checked++;
    });
    return Math.round((checked / keys.length) * 100);
}

// Get interest level badge HTML
function getInterestLevelBadge(level) {
  const badges = {
    Hot: '<span class="badge bg-danger">Hot</span>',
    Warm: '<span class="badge bg-warning">Warm</span>',
    Cold: '<span class="badge bg-info">Cold</span>',
  }
  return badges[level] || '<span class="badge bg-secondary">' + level + "</span>"
}

// Show add form modal
window.showAddModal = function showAddModal() {
  document.getElementById("modalTitle").textContent = "Add New Recruited Agent"
  document.getElementById("recruitmentForm").reset()
  document.getElementById("leadId").value = ""
  
  // Reset form fields
  const form = document.getElementById("recruitmentForm")
  if (form) {
    form.reset()
  }
  
  // Set to add mode - show text inputs, hide selects
  setModalMode('add')
  
  // Show the modal
  document.getElementById('recruitmentModal').classList.remove('hidden')
  document.body.classList.add('overflow-hidden')
}

// Set modal mode (add or edit)
function setModalMode(mode) {
  const isEditMode = mode === 'edit'
  
  // Manager field
  const managerName = document.getElementById('managerName')
  const managerSelect = document.getElementById('managerSelect')
  const managerLabel = document.getElementById('managerLabel')
  
  if (managerName && managerSelect && managerLabel) {
    if (isEditMode) {
      managerName.classList.add('hidden')
      managerSelect.classList.remove('hidden')
      managerLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>'
    } else {
      managerName.classList.remove('hidden')
      managerSelect.classList.add('hidden')
      managerLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>'
    }
  }
  
  // Team field
  const teamNameDiv = document.getElementById('teamNameDiv')
  const teamSelect = document.getElementById('teamSelect')
  const teamSelectArrow = document.getElementById('teamSelectArrow')
  const teamLabel = document.getElementById('teamLabel')
  
  if (teamNameDiv && teamSelect && teamLabel) {
    if (isEditMode) {
      teamNameDiv.classList.add('hidden')
      teamSelect.classList.remove('hidden')
      teamSelectArrow.classList.remove('hidden')
      teamLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>'
    } else {
      teamNameDiv.classList.remove('hidden')
      teamSelect.classList.add('hidden')
      teamSelectArrow.classList.add('hidden')
      teamLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>'
    }
  }
  
  // Source field
  const source = document.getElementById('source')
  const sourceSelect = document.getElementById('sourceSelect')
  const sourceLabel = document.getElementById('sourceLabel')
  
  if (source && sourceSelect && sourceLabel) {
    if (isEditMode) {
      source.classList.add('hidden')
      sourceSelect.classList.remove('hidden')
      sourceLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>'
    } else {
      source.classList.remove('hidden')
      sourceSelect.classList.add('hidden')
      sourceLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>'
    }
  }
}

// Edit lead
window.editLead = function editLead(id) {
  const formData = new FormData()
  formData.append("action", "get_recruitment_leads")
  formData.append("filters", JSON.stringify({ id: id }))

  fetch("includes/functions.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.data.length > 0) {
        const lead = data.data[0]

        // Set modal title
        const modalTitle = document.getElementById("modalTitle")
        if (modalTitle) modalTitle.textContent = "Edit Agent Information"
        
        // Populate form fields with null checks
        const leadId = document.getElementById("leadId")
        if (leadId) leadId.value = lead.id
        
        const fullName = document.getElementById("fullName")
        if (fullName) fullName.value = lead.full_name
        
        const contactNumber = document.getElementById("contactNumber")
        if (contactNumber) contactNumber.value = lead.contact_number
        
        const email = document.getElementById("email")
        if (email) email.value = lead.email || ""
        
        const timestamp = document.getElementById("timestamp")
        if (timestamp) timestamp.value = lead.created_at ? lead.created_at.split(' ')[0] : ""
        
        const status = document.getElementById("status")
        if (status) status.value = lead.status
        
        const remarks = document.getElementById("remarks")
        if (remarks) remarks.value = lead.remarks || ""

        // Set to edit mode - show dropdowns, hide text inputs
        setModalMode('edit')

        // Populate the dropdown fields for edit mode
        if (document.getElementById("managerSelect")) {
          document.getElementById("managerSelect").value = lead.manager_id || ""
        }
        if (document.getElementById("teamSelect")) {
          document.getElementById("teamSelect").value = lead.team_id || ""
        }
        if (document.getElementById("sourceSelect")) {
          document.getElementById("sourceSelect").value = lead.source_id || ""
        }

        // Populate checkboxes for recruitment progress
        const checkboxFields = [
          'pre-assessment', 'accreditation', 'assessment', 'sales_training', 
          'site_tour', 'focus_projects', 'habit_forming', 'digital_training',
          'sales_training_materials', 'objection_handling', 'VAST', 
          'sales_monitoring', 'LMS', 'comm_structure'
        ]
        
        checkboxFields.forEach(field => {
          const checkbox = document.getElementById(field)
          if (checkbox && lead[field.replace('-', '_')]) {
            checkbox.checked = lead[field.replace('-', '_')] === '1' || lead[field.replace('-', '_')] === 1
          }
        })

        // Show the modal with null check
        const modal = document.getElementById('recruitmentModal')
        if (modal) {
          modal.classList.remove('hidden')
          document.body.classList.add('overflow-hidden')
        }
      }
    })
    .catch((error) => {
      console.error("Error fetching lead data:", error)
      showAlert("Error loading lead data", "danger")
    })
}

// Hide recruitment modal
window.hideRecruitmentModal = function hideRecruitmentModal() {
  document.getElementById('recruitmentModal').classList.add('hidden')
  document.body.classList.remove('overflow-hidden')
}

// Save recruitment lead (add or update)
window.saveRecruitmentLead = function saveRecruitmentLead() {
  const form = document.getElementById("recruitmentForm")
  const formData = new FormData(form)

  const leadId = document.getElementById("leadId").value
  const action = leadId ? "update_recruitment_lead" : "add_recruitment_lead"
  formData.append("action", action)

  // Handle dropdown values in edit mode
  if (leadId) {
    const managerSelect = document.getElementById("managerSelect")
    const teamSelect = document.getElementById("teamSelect")
    const sourceSelect = document.getElementById("sourceSelect")
    
    if (managerSelect && !managerSelect.classList.contains('hidden')) {
      formData.set("manager_id", managerSelect.value)
    }
    if (teamSelect && !teamSelect.classList.contains('hidden')) {
      formData.set("team_id", teamSelect.value)
    }
    if (sourceSelect && !sourceSelect.classList.contains('hidden')) {
      formData.set("source_id", sourceSelect.value)
    }
  }

  fetch("includes/functions.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert(data.message, "success")
        hideRecruitmentModal()
        loadRecruitmentData()
        loadStats()
      } else {
        showAlert("Error: " + data.message, "danger")
      }
    })
    .catch((error) => {
      console.error("Error saving lead:", error)
      showAlert("Error saving lead", "danger")
    })
}

// Delete lead
window.deleteLead = function deleteLead(id) {
  if (confirm("Are you sure you want to delete this recruitment lead?")) {
    const formData = new FormData()
    formData.append("action", "delete_recruitment_lead")
    formData.append("id", id)

    fetch("includes/functions.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showAlert(data.message, "success")
          loadRecruitmentData()
          loadStats()
        } else {
          showAlert("Error: " + data.message, "danger")
        }
      })
      .catch((error) => {
        console.error("Error deleting lead:", error)
        showAlert("Error deleting lead", "danger")
      })
  }
}

function applyFilters() {
    console.log('Applying filters...');
    
    const status = document.getElementById("filterStatus").value.trim();
    const source = document.getElementById("filterSource").value.trim();
    const search = document.getElementById("searchInput").value.trim();
    const team = document.getElementById("filterTeam").value.trim();
    const recruitmentStatus = document.getElementById("filterRecruitmentStatus").value.trim();
    
    currentFilters = {};
    if (status) currentFilters.status = status;
    if (source) currentFilters.source = source;
    if (search) currentFilters.search = search;
    if (team) currentFilters.team = team;
    if (recruitmentStatus !== '') {
        currentFilters.onboardStatus = recruitmentStatus;
        console.log('Setting onboarding filter to:', recruitmentStatus);
    }
    
    loadRecruitmentData();
}

// Sort table
function sortTable(column) {
  if (currentSort.column === column) {
    currentSort.order = currentSort.order === "ASC" ? "DESC" : "ASC"
  } else {
    currentSort.column = column
    currentSort.order = "ASC"
  }

  loadRecruitmentData()
}

// Clear filters
window.clearFilters = function clearFilters() {
  // Reset all filter inputs
  document.getElementById("searchInput").value = ""
  document.getElementById("filterStatus").value = "" // Reset to "All Status"
  document.getElementById("filterOnboardStatus").value = ""
  if (document.getElementById("filterTeam")) {
    document.getElementById("filterTeam").value = ""
  }
  
  // Clear current filters and reload data
  currentFilters = {}
  loadRecruitmentData()
}

// Refresh data
window.refreshData = function refreshData() {
  loadRecruitmentData()
}

// Show alert
function showAlert(message, type) {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`
  alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

  // Insert at the top of the main content
  const dashboard = document.querySelector(".recruitment-dashboard")
  dashboard.insertBefore(alertDiv, dashboard.firstChild)

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    if (alertDiv.parentNode) {
      alertDiv.remove()
    }
  }, 5000)
}
