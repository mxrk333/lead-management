// Enhanced notification handling with better error handling and persistence

class NotificationManager {
  constructor() {
    this.init()
  }

  init() {
    this.setupEventListeners()
    this.createMobileOverlay()
  }

  createMobileOverlay() {
    if (!document.querySelector(".mobile-overlay")) {
      const mobileOverlay = document.createElement("div")
      mobileOverlay.className = "mobile-overlay"
      document.body.appendChild(mobileOverlay)

      mobileOverlay.addEventListener("click", () => {
        this.closeAllDropdowns()
      })
    }
  }

  setupEventListeners() {
    // Notification dropdown
    const notificationBtn = document.querySelector(".notification-btn")
    const notificationDropdown = document.querySelector(".notification-dropdown")

    if (notificationBtn && notificationDropdown) {
      this.setupNotificationDropdown(notificationBtn, notificationDropdown)
    }

    // User menu dropdown
    const userMenuTrigger = document.querySelector(".user-menu-trigger")
    const userMenuDropdown = document.querySelector(".user-menu-dropdown")

    if (userMenuTrigger && userMenuDropdown) {
      this.setupUserMenuDropdown(userMenuTrigger, userMenuDropdown)
    }

    // Close dropdowns when clicking outside (desktop)
    document.addEventListener("click", (e) => {
      if (window.innerWidth > 768) {
        if (notificationDropdown && !notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
          notificationDropdown.classList.remove("active")
        }

        if (userMenuDropdown && !userMenuDropdown.contains(e.target) && !userMenuTrigger.contains(e.target)) {
          userMenuDropdown.classList.remove("active")
          userMenuTrigger.classList.remove("active")
        }
      }
    })

    // Handle window resize
    window.addEventListener("resize", () => {
      this.handleResize()
    })
  }

  setupNotificationDropdown(btn, dropdown) {
    // Add mobile close button
    if (!dropdown.querySelector(".mobile-close")) {
      const closeBtn = document.createElement("button")
      closeBtn.className = "mobile-close"
      closeBtn.innerHTML = '<i class="fas fa-times"></i>'
      closeBtn.style.display = "none"
      dropdown.appendChild(closeBtn)

      closeBtn.addEventListener("click", (e) => {
        e.stopPropagation()
        this.closeNotificationDropdown()
      })
    }

    btn.addEventListener("click", (e) => {
      e.stopPropagation()
      e.preventDefault()
      this.toggleNotificationDropdown()
    })
  }

  setupUserMenuDropdown(trigger, dropdown) {
    // Add mobile close button
    if (!dropdown.querySelector(".mobile-close")) {
      const closeBtn = document.createElement("button")
      closeBtn.className = "mobile-close"
      closeBtn.innerHTML = '<i class="fas fa-times"></i>'
      closeBtn.style.display = "none"
      dropdown.appendChild(closeBtn)

      closeBtn.addEventListener("click", (e) => {
        e.stopPropagation()
        this.closeUserMenuDropdown()
      })
    }

    trigger.addEventListener("click", (e) => {
      e.stopPropagation()
      e.preventDefault()
      this.toggleUserMenuDropdown()
    })
  }

  toggleNotificationDropdown() {
    const dropdown = document.querySelector(".notification-dropdown")
    const userDropdown = document.querySelector(".user-menu-dropdown")
    const userTrigger = document.querySelector(".user-menu-trigger")

    // Close user menu if open
    if (userDropdown) {
      userDropdown.classList.remove("active")
      userTrigger.classList.remove("active")
    }

    // Toggle notification dropdown
    const isActive = dropdown.classList.contains("active")
    dropdown.classList.toggle("active")

    this.handleMobileState(!isActive, dropdown)
  }

  toggleUserMenuDropdown() {
    const dropdown = document.querySelector(".user-menu-dropdown")
    const trigger = document.querySelector(".user-menu-trigger")
    const notificationDropdown = document.querySelector(".notification-dropdown")

    // Close notification dropdown if open
    if (notificationDropdown) {
      notificationDropdown.classList.remove("active")
    }

    // Toggle user menu
    const isActive = dropdown.classList.contains("active")
    dropdown.classList.toggle("active")
    trigger.classList.toggle("active")

    this.handleMobileState(!isActive, dropdown)
  }

  handleMobileState(isOpening, dropdown) {
    const overlay = document.querySelector(".mobile-overlay")
    const closeBtn = dropdown.querySelector(".mobile-close")

    if (window.innerWidth <= 768) {
      if (isOpening) {
        overlay.classList.add("active")
        if (closeBtn) closeBtn.style.display = "flex"
        document.body.style.overflow = "hidden"
      } else {
        overlay.classList.remove("active")
        if (closeBtn) closeBtn.style.display = "none"
        document.body.style.overflow = "auto"
      }
    }
  }

  closeNotificationDropdown() {
    const dropdown = document.querySelector(".notification-dropdown")
    dropdown.classList.remove("active")
    this.handleMobileState(false, dropdown)
  }

  closeUserMenuDropdown() {
    const dropdown = document.querySelector(".user-menu-dropdown")
    const trigger = document.querySelector(".user-menu-trigger")
    dropdown.classList.remove("active")
    trigger.classList.remove("active")
    this.handleMobileState(false, dropdown)
  }

  closeAllDropdowns() {
    this.closeNotificationDropdown()
    this.closeUserMenuDropdown()
  }

  handleResize() {
    document.body.style.overflow = "auto"
    const overlay = document.querySelector(".mobile-overlay")
    overlay.classList.remove("active")

    // Reset close buttons on desktop
    if (window.innerWidth > 768) {
      document.querySelectorAll(".mobile-close").forEach((btn) => {
        btn.style.display = "none"
      })
    }
  }

  // Mark all notifications as read
  markAllNotificationsAsRead() {
    console.log("markAllNotificationsAsRead called")

    // Show loading state
    const markText = document.querySelector(".mark-text")
    const loadingSpinner = document.querySelector(".loading-spinner")
    if (markText) markText.style.display = "none"
    if (loadingSpinner) loadingSpinner.style.display = "inline-block"

    // Update UI immediately
    const unreadItems = document.querySelectorAll(".notification-item.unread")
    unreadItems.forEach((item) => {
      item.classList.remove("unread")
      item.classList.add("read")
    })

    this.updateNotificationBadge()

    // Send request to server
    fetch("mark-notifications-read.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=mark_all_read",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        return response.json()
      })
      .then((data) => {
        console.log("Server response:", data)

        if (data.success) {
          // Hide the mark all as read button
          const markAllBtn = document.querySelector(".mark-all-read")
          if (markAllBtn) {
            markAllBtn.style.display = "none"
          }

          this.showSuccessMessage("All notifications marked as read!")
        } else {
          throw new Error(data.error || "Unknown error occurred")
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        this.showErrorMessage("Failed to mark notifications as read: " + error.message)

        // Revert UI changes on error
        unreadItems.forEach((item) => {
          item.classList.add("unread")
          item.classList.remove("read")
        })
        this.updateNotificationBadge()
      })
      .finally(() => {
        // Hide loading state
        if (markText) markText.style.display = "inline"
        if (loadingSpinner) loadingSpinner.style.display = "none"
      })
  }

  updateNotificationBadge() {
    const unreadItems = document.querySelectorAll(".notification-item.unread")
    const badge = document.querySelector(".notification-badge")
    const markAllBtn = document.querySelector(".mark-all-read")

    if (unreadItems.length === 0) {
      if (badge) badge.style.display = "none"
      if (markAllBtn) markAllBtn.style.display = "none"
    } else {
      if (badge) {
        badge.textContent = unreadItems.length
        badge.style.display = "flex"
      }
      if (markAllBtn) markAllBtn.style.display = "inline"
    }
  }

  handleNotificationClick(element) {
    const url = element.getAttribute("data-url")

    // Mark as read visually
    element.classList.remove("unread")
    element.classList.add("read")

    this.updateNotificationBadge()
    this.closeAllDropdowns()

    // Navigate to the URL
    if (url) {
      window.location.href = url
    }
  }

  showSuccessMessage(message) {
    this.showMessage(message, "success")
  }

  showErrorMessage(message) {
    this.showMessage(message, "error")
  }

  showMessage(message, type = "success") {
    const existingMsg = document.querySelector(".notification-message")
    if (existingMsg) {
      existingMsg.remove()
    }

    const msg = document.createElement("div")
    msg.className = "notification-message"
    msg.textContent = message
    msg.style.cssText = `
            position: fixed; 
            top: 20px; 
            right: 20px; 
            background: ${type === "success" ? "#10b981" : "#ef4444"}; 
            color: white; 
            padding: 12px 20px; 
            border-radius: 8px; 
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
            max-width: 300px;
        `

    document.body.appendChild(msg)

    setTimeout(() => {
      if (msg.parentNode) {
        msg.parentNode.removeChild(msg)
      }
    }, 4000)
  }
}

// Initialize notification manager when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.notificationManager = new NotificationManager()

  // Make functions globally available for backward compatibility
  window.markAllNotificationsAsRead = () => {
    window.notificationManager.markAllNotificationsAsRead()
    return false
  }

  window.handleNotificationClick = (element) => {
    window.notificationManager.handleNotificationClick(element)
  }

  window.clearSearch = () => {
    const searchInput = document.querySelector(".search-input")
    if (searchInput) {
      searchInput.value = ""
      searchInput.focus()
      searchInput.closest("form").submit()
    }
  }
})
