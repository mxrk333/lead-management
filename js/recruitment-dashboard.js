// recruitment-dashboard.js
// JavaScript for recruitment dashboard functionality

const currentSort = { column: "created_at", order: "DESC" }
let currentFilters = {}
const bootstrap = window.bootstrap // Declare the bootstrap variable

// Initialize dashboard
document.addEventListener("DOMContentLoaded", () => {
  loadStats()
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
  document.getElementById("loadingIndicator").style.display = "block"

  const formData = new FormData()
  formData.append("action", "get_recruitment_leads")
  formData.append("filters", JSON.stringify(currentFilters))
  formData.append("sort_by", currentSort.column)
  formData.append("sort_order", currentSort.order)

  fetch("includes/functions.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      document.getElementById("loadingIndicator").style.display = "none"

      if (data.success) {
        displayRecruitmentData(data.data)
      } else {
        showAlert("Error loading data: " + data.message, "danger")
      }
    })
    .catch((error) => {
      document.getElementById("loadingIndicator").style.display = "none"
      console.error("Error loading data:", error)
      showAlert("Error loading data", "danger")
    })
}

// Display recruitment data in table
function displayRecruitmentData(leads) {
  const tbody = document.getElementById("recruitmentTableBody")
  tbody.innerHTML = ""

  if (leads.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center">No recruitment leads found</td></tr>'
    return
  }

  leads.forEach((lead) => {
    const row = document.createElement("tr")

    // Format timestamp
    const timestamp = new Date(lead.created_at).toLocaleString()

    // Interest level badge
    const interestBadge = getInterestLevelBadge(lead.interest_level)

    row.innerHTML = `
            <td>${timestamp}</td>
            <td>${lead.full_name}</td>
            <td>${lead.contact_number}</td>
            <td>${lead.email || ""}</td>
            <td>${lead.recruiter_name || ""}</td>
            <td>${interestBadge}</td>
            <td><span class="badge bg-secondary">${lead.status}</span></td>
            <td>${lead.source}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editLead(${lead.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteLead(${lead.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `

    tbody.appendChild(row)
  })
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
function showAddModal() {
  document.getElementById("modalTitle").textContent = "Add New Recruitment Lead"
  document.getElementById("recruitmentForm").reset()
  document.getElementById("leadId").value = ""

  const modal = new bootstrap.Modal(document.getElementById("recruitmentModal"))
  modal.show()
}

// Edit lead
function editLead(id) {
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

        document.getElementById("modalTitle").textContent = "Edit Recruitment Lead"
        document.getElementById("leadId").value = lead.id
        document.getElementById("fullName").value = lead.full_name
        document.getElementById("contactNumber").value = lead.contact_number
        document.getElementById("email").value = lead.email || ""
        document.getElementById("recruiterName").value = lead.recruiter_name || ""
        document.getElementById("interestLevel").value = lead.interest_level
        document.getElementById("status").value = lead.status
        document.getElementById("source").value = lead.source
        document.getElementById("agentOnboardingStatus").value = lead.agent_onboarding_status || ""
        document.getElementById("remarks").value = lead.remarks || ""

        const modal = new bootstrap.Modal(document.getElementById("recruitmentModal"))
        modal.show()
      }
    })
    .catch((error) => {
      console.error("Error fetching lead data:", error)
      showAlert("Error loading lead data", "danger")
    })
}

// Save recruitment lead (add or update)
function saveRecruitmentLead() {
  const form = document.getElementById("recruitmentForm")
  const formData = new FormData(form)

  const leadId = document.getElementById("leadId").value
  const action = leadId ? "update_recruitment_lead" : "add_recruitment_lead"
  formData.append("action", action)

  fetch("includes/functions.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert(data.message, "success")
        bootstrap.Modal.getInstance(document.getElementById("recruitmentModal")).hide()
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
function deleteLead(id) {
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

// Apply filters
function applyFilters() {
  currentFilters = {}

  const status = document.getElementById("filterStatus").value
  const interest = document.getElementById("filterInterest").value
  const source = document.getElementById("filterSource").value
  const search = document.getElementById("searchInput").value

  if (status) currentFilters.status = status
  if (interest) currentFilters.interest_level = interest
  if (source) currentFilters.source = source
  if (search) currentFilters.search = search

  loadRecruitmentData()
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

// Refresh data
function refreshData() {
  loadStats()
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
