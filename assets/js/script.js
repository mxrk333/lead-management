document.addEventListener("DOMContentLoaded", () => {
  // Toggle sidebar on mobile
  const sidebarToggle = document.getElementById("sidebar-toggle")
  const sidebar = document.querySelector(".sidebar")

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("active")
    })
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener("click", (event) => {
    if (
      window.innerWidth <= 768 &&
      !event.target.closest(".sidebar") &&
      !event.target.closest("#sidebar-toggle") &&
      sidebar.classList.contains("active")
    ) {
      sidebar.classList.remove("active")
    }
  })

  // User menu dropdown toggle
  const userMenuTrigger = document.querySelector(".user-menu-trigger")
  const userMenuDropdown = document.querySelector(".user-menu-dropdown")

  if (userMenuTrigger && userMenuDropdown) {
    userMenuTrigger.addEventListener("click", (event) => {
      event.stopPropagation()
      userMenuDropdown.style.display = userMenuDropdown.style.display === "block" ? "none" : "block"
    })

    // Close user menu when clicking outside
    document.addEventListener("click", (event) => {
      if (!event.target.closest(".header-user-menu")) {
        userMenuDropdown.style.display = "none"
      }
    })
  }

  // Form validation for add/edit lead
  const leadForm = document.querySelector(".lead-form")

  if (leadForm) {
    leadForm.addEventListener("submit", (event) => {
      const requiredFields = leadForm.querySelectorAll("[required]")
      let isValid = true

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false
          field.classList.add("error")
        } else {
          field.classList.remove("error")
        }
      })

      if (!isValid) {
        event.preventDefault()
        alert("Please fill in all required fields")
      }
    })
  }

  // Notification dropdown
  const notificationIcon = document.querySelector(".header-notification")

  if (notificationIcon) {
    notificationIcon.addEventListener("click", () => {
      // Implement notification dropdown functionality
      alert("Notifications feature coming soon!")
    })
  }
})
