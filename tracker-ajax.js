// Function to load tracker data from database
function loadTrackerData(leadId) {
    return fetch(`database-functions.php?action=get_tracker_data&lead_id=${leadId}`)
      .then((response) => response.json())
      .then((data) => {
        console.log("Loaded tracker data from database:", data)
        return data
      })
      .catch((error) => {
        console.error("Error loading tracker data:", error)
        return null
      })
  }
  
  let currentLeadData
  let currentTrackerData
  let initialReservationDate
  let loadUploadedReceipts
  let populateEditForm
  let toggleMode
  
  async function openDpDetailsModal(leadId, clientName, developer, projectModel, price, trackerData, mode = "view") {
    // Store basic lead info
    currentLeadData = { leadId, clientName, developer, projectModel, price }
  
    try {
      currentTrackerData = await loadTrackerData(leadId)
      console.log("Fresh tracker data from database:", currentTrackerData)
    } catch (error) {
      console.error("Error fetching tracker data:", error)
      currentTrackerData = null
    }
  
    initialReservationDate = currentTrackerData ? currentTrackerData.reservation_date : null
  
    // Set basic info for both view and edit sections
    document.getElementById("view_client_name").textContent = clientName
    document.getElementById("view_developer").textContent = developer
    document.getElementById("view_project_model").textContent = projectModel
    document.getElementById("edit_client_name").value = clientName
    document.getElementById("edit_project_details").value = developer + " - " + projectModel
  
    if (price && price > 0) {
      const formattedPrice =
        "₱" +
        Number.parseFloat(price).toLocaleString("en-US", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })
      document.getElementById("view_price").textContent = formattedPrice
    } else {
      document.getElementById("view_price").textContent = "Price not set"
    }
  
    // Load uploaded receipts for this lead
    loadUploadedReceipts(leadId)
  
    populateEditForm(currentTrackerData)
  
    // Display in requested mode
    toggleMode(mode)
  
    // Show the modal
    document.getElementById("dpDetailsModal").style.display = "block"
    document.body.style.overflow = "hidden"
  }
  
  function saveTrackerData() {
    const formData = new FormData()
    formData.append("action", "update_tracker_data")
    formData.append("lead_id", currentLeadData.leadId)
    formData.append("reservation_date", document.getElementById("edit_reservation_date").value)
  
    if (document.getElementById("edit_requirements_complete").checked) {
      formData.append("requirements_complete", "1")
    }
    if (document.getElementById("edit_pagibig_bank_approval").checked) {
      formData.append("pagibig_bank_approval", "1")
    }
    if (document.getElementById("edit_loan_takeout").checked) {
      formData.append("loan_takeout", "1")
    }
    if (document.getElementById("edit_turnover").checked) {
      formData.append("turnover", "1")
    }
    if (document.getElementById("edit_spot_dp").checked) {
      formData.append("spot_dp", "1")
    }
  
    formData.append("dp_terms", document.getElementById("edit_dp_terms").value)
    formData.append("current_dp_stage", document.getElementById("edit_current_dp_stage").value)
  
    return fetch("database-functions.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          console.log("Tracker data saved successfully")
          // Refresh the modal with updated data
          return loadTrackerData(currentLeadData.leadId)
        } else {
          throw new Error("Failed to save tracker data")
        }
      })
      .catch((error) => {
        console.error("Error saving tracker data:", error)
        alert("Error saving data. Please try again.")
      })
  }
  