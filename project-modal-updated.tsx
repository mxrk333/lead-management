// Updated project modal content generation function for projectlisting.php
// This function should replace the generateProjectModalContent function

function generateProjectModalContent(project) {
  const makePath = (img) => {
    if (!img) return null
    // If already contains a slash, assume it is a full path
    return img.includes("/") ? img : `uploads/projects/${img}`
  }
  const images = [
    makePath(project.image1),
    makePath(project.image2),
    makePath(project.image3),
    makePath(project.image4),
  ].filter(Boolean)

  let imageGallery = ""
  if (images.length > 0) {
    imageGallery = `
            <div class="mb-6">
                <div class="w-full h-96 overflow-hidden rounded-xl mb-4 shadow-lg">
                    <img id="main-image" src="${images[0]}" alt="${project.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='images/placeholder-property.png';">
                </div>
                ${
                  images.length > 1
                    ? `
                    <div class="grid grid-cols-4 gap-2">
                        ${images
                          .map(
                            (img, index) => `
                            <div class="h-20 overflow-hidden rounded-lg shadow-sm cursor-pointer" onclick="changeMainImage('${img}', ${index})">
                                <img src="${img}" alt="${project.name} ${index + 1}" class="w-full h-full object-cover border-2 ${index === 0 ? "border-blue-primary" : "border-transparent"} hover:border-blue-primary transition" id="thumb-${index}" onerror="this.onerror=null; this.src='images/placeholder-property.png';">
                            </div>
                        `,
                          )
                          .join("")}
                    </div>
                `
                    : ""
                }
            </div>
        `
  }

  const statusMap = {
    rfo: "RFO (Ready For Occupancy)",
    preselling: "Preselling",
    ogc: "OGC (On Going Construction)",
    rfo_preselling: "RFO/Preselling",
    preselling_ogc: "Preselling/OGC",
  }
  const formattedStatus = statusMap[project.status] || project.status

  // Format financial details
  const formatCurrency = (amount) => {
    if (!amount || amount == 0) return "Not specified"
    return "₱" + Number(amount).toLocaleString()
  }

  const formatPercentage = (percentage) => {
    if (!percentage || percentage == 0) return "Not specified"
    return percentage + "%"
  }

  // Build financial details section
  let financialDetails = ""
  if (
    project.total_contract_price ||
    project.reservation_fee ||
    project.bank_amortization ||
    project.required_salary ||
    project.downpayment_percentage ||
    project.monthly_downpayment_3mos ||
    project.monthly_downpayment_6mos ||
    project.monthly_downpayment_12mos ||
    project.monthly_downpayment_18mos
  ) {
    financialDetails = `
            <div class="bg-blue-50 rounded-2xl p-6 mb-8">
                <h4 class="text-2xl font-semibold text-blue-primary mb-4 flex items-center">
                    <i class="fas fa-calculator mr-3"></i>Financial Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    ${
                      project.total_contract_price
                        ? `
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Total Contract Price</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800 font-semibold">${formatCurrency(project.total_contract_price)}</span>
                            </div>
                        </div>
                    `
                        : ""
                    }
                    ${
                      project.reservation_fee
                        ? `
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Reservation Fee</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800 font-semibold">${formatCurrency(project.reservation_fee)}</span>
                            </div>
                        </div>
                    `
                        : ""
                    }
                    ${
                      project.bank_amortization
                        ? `
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Bank Amortization</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800 font-semibold">${formatCurrency(project.bank_amortization)}</span>
                            </div>
                        </div>
                    `
                        : ""
                    }
                    ${
                      project.required_salary
                        ? `
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Required Salary</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800 font-semibold">${formatCurrency(project.required_salary)}</span>
                            </div>
                        </div>
                    `
                        : ""
                    }
                    ${
                      project.downpayment_percentage
                        ? `
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Downpayment</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800 font-semibold">${formatPercentage(project.downpayment_percentage)}</span>
                            </div>
                        </div>
                    `
                        : ""
                    }
                </div>
                
                ${
                  project.monthly_downpayment_3mos ||
                  project.monthly_downpayment_6mos ||
                  project.monthly_downpayment_12mos ||
                  project.monthly_downpayment_18mos
                    ? `
                    <div class="mt-6">
                        <h5 class="text-lg font-semibold text-gray-700 mb-3">Monthly Downpayment Options</h5>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            ${
                              project.monthly_downpayment_3mos
                                ? `
                                <div class="text-center p-3 bg-white rounded-lg border border-blue-200">
                                    <div class="text-sm text-gray-600">3 Months</div>
                                    <div class="text-lg font-semibold text-blue-primary">${formatCurrency(project.monthly_downpayment_3mos)}</div>
                                </div>
                            `
                                : ""
                            }
                            ${
                              project.monthly_downpayment_6mos
                                ? `
                                <div class="text-center p-3 bg-white rounded-lg border border-blue-200">
                                    <div class="text-sm text-gray-600">6 Months</div>
                                    <div class="text-lg font-semibold text-blue-primary">${formatCurrency(project.monthly_downpayment_6mos)}</div>
                                </div>
                            `
                                : ""
                            }
                            ${
                              project.monthly_downpayment_12mos
                                ? `
                                <div class="text-center p-3 bg-white rounded-lg border border-blue-200">
                                    <div class="text-sm text-gray-600">12 Months</div>
                                    <div class="text-lg font-semibold text-blue-primary">${formatCurrency(project.monthly_downpayment_12mos)}</div>
                                </div>
                            `
                                : ""
                            }
                            ${
                              project.monthly_downpayment_18mos
                                ? `
                                <div class="text-center p-3 bg-white rounded-lg border border-blue-200">
                                    <div class="text-sm text-gray-600">18 Months</div>
                                    <div class="text-lg font-semibold text-blue-primary">${formatCurrency(project.monthly_downpayment_18mos)}</div>
                                </div>
                            `
                                : ""
                            }
                        </div>
                    </div>
                `
                    : ""
                }
            </div>
        `
  }

  return `
        <div class="flex flex-col lg:flex-row gap-6">
            <div class="lg:w-1/2">
                ${imageGallery}
            </div>
            <div class="lg:w-1/2">
                <h2 id="modal-title" class="text-3xl font-bold text-gray-800 mb-4">${project.name}</h2>
                
                <div class="flex flex-wrap gap-3 mb-6">
                    <span class="price-badge px-4 py-2 rounded-lg text-lg font-bold">
                        ${project.commission}% COMM
                    </span>
                    <span class="priority-${project.priority} px-4 py-2 rounded-lg text-lg font-semibold">
                        ${project.priority.charAt(0).toUpperCase() + project.priority.slice(1)} Priority
                    </span>
                </div>

                <div class="mb-8">
                    <span class="text-4xl font-bold text-blue-primary bg-blue-light px-6 py-3 rounded-xl inline-block">
                        ₱${Number(project.price_min).toLocaleString()} - ₱${Number(project.price_max).toLocaleString()}
                    </span>
                </div>

                ${financialDetails}

                <div class="bg-gray-50 rounded-2xl p-6 mb-8">
                    <h4 class="text-2xl font-semibold text-gray-800 mb-4">Project Details</h4>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">House Model</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.house_model || "Not specified"}</span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">House Type</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.description || "Not specified"}</span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Construction Status</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${formattedStatus}</span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Developer</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.developer || "Not specified"}</span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0">
                                <span class="text-base text-gray-600 font-medium">Location</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.city_name}, ${project.province_name}</span>
                                ${project.exact_location ? `<br><span class="text-sm text-gray-600">${project.exact_location}</span>` : ""}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    ${
                      project.drive_link
                        ? `
                        <a href="${project.drive_link}" target="_blank" rel="noopener noreferrer"
                           class="w-full flex items-center justify-center px-6 py-3 btn-primary text-white rounded-xl text-lg font-semibold transition-all duration-300 focus-ring">
                            <i class="fab fa-google-drive mr-3"></i>
                            View on Google Drive
                        </a>
                    `
                        : ""
                    }
                    
                    ${
                      project.messenger_link
                        ? `
                        <a href="#" data-messenger-link="${project.messenger_link}"
                           class="w-full flex items-center justify-center px-6 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300 focus-ring">
                            <i class="fab fa-facebook-messenger mr-3"></i>
                            Contact via Messenger
                        </a>
                    `
                        : ""
                    }
                </div>
            </div>
        </div>
    `
}
