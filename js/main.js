// combine all js and put it in here
// but be careful and watchout for variables of other things

(function () {
  // ---------- Helper Functions ----------
  function setActiveNav(pagePath) {
    document.querySelectorAll(".nav-item").forEach((a) => {
      a.classList.toggle("active", a.dataset.page === pagePath);
    });
  }

  function isFullHTML(text) {
    const t = text.trim().toLowerCase();
    return t.startsWith("<!doctype") || t.startsWith("<html");
  }

  window.isAtLeast18YearsOld = function (dob) {
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
    return age >= 18;
  };

  function showTableLoader(tableSelector, colspan = 5) {
    $(tableSelector).html(
      `<tr>
      <td colspan="${colspan}" style="text-align:center;">
        <i class="fas fa-spinner fa-spin" style="font-size:30px;color:#007bff"></i>
        <br>Loading...
      </td>
    </tr>`
    );
  }

  function showMainContentLoader() {
    document.getElementById("main-content").innerHTML = `
    <div style="display:flex;justify-content:center;align-items:center;min-height:300px;">
      <i class="fas fa-spinner fa-spin" style="font-size:60px;color:#007bff"></i>
    </div>`;
  }

  // ---------- AJAX Page Loader ----------
  async function loadPage(pagePath) {
    const container = document.getElementById("main-content");
    if (!container) return;
    container.innerHTML = `
    <div style=" display: flex; justify-content: center; 
      align-items: center; height: 100%; min-height: 300px; ">
      <i class="fas fa-spinner fa-spin" style="font-size:60px; color:#007bff;"></i>
    </div>`;

    try {
      const res = await fetch(pagePath, {
        method: "GET",
        credentials: "same-origin",
      });
      const text = await res.text();

      if (isFullHTML(text)) {
        window.location.href = "index.php";
        return;
      }

      container.innerHTML = text;

      // rebind dynamic content events
      initServiceModals();
      initViewAccount();
      initViewServiceButtons();
      initViewPatientButtons();
      initPatientAvailedServiceButtons();
      initAvailServiceButton();
      addPatientServiceHandlerOnce();
      bindAccountFilterEvents();
      addAccountViewHandlerOnce();
      addPatientViewHandlerOnce();
      addServiceViewHandlerOnce();

      if (typeof initAddAccountButton === "function") initAddAccountButton();

      if (typeof initAddPatientButton === "function") initAddPatientButton();

      if (typeof initDiscountModals === "function") initDiscountModals();

      if (pagePath.includes("home_personnel.php")) {
        initRequestModals();
        bindPersonnelTableEvents();
      }

      if (pagePath.includes("home_recep.php")) {
        bindRecepTableEvents();
      }

      if (pagePath.includes("home_ad.php")) {
        bindAdminTableEvents();
      }

      if (pagePath.includes("reports.php")) {
        initReportControls();
        initReportFiltersAJAX();
        initReportExportExcel();
      }

      if (pagePath.includes("logs.php")) {
        bindLogsFilterEvents();
      }

      // if (typeof initPackagesModals === "function" && pagePath.includes('packages.php')) {
      //     initPackagesModals();
      // }

      if (typeof initPackagesModals === "function") initPackagesModals();

      if (typeof window.partialInit === "function") {
        try {
          window.partialInit();
        } catch (err) {
          console.error("partialInit error", err);
        }
      }

      setActiveNav(pagePath);
    } catch (err) {
      console.error(err);
      container.innerHTML = "<p>ERROR LOADING PAGE</p>";
    }
  }

  // ---------- Sidebar Nav ----------
  document.addEventListener("click", function (e) {
    const navItem = e.target.closest && e.target.closest(".nav-item");
    if (!navItem) return;
    e.preventDefault();
    const pagePath = navItem.dataset.page;
    if (!pagePath) return;

    // special edit for handling my profile
    if (pagePath.includes("accounts/view_account.php")) {
      document
        .querySelectorAll(".nav-item")
        .forEach((a) => a.classList.remove("active"));
      navItem.classList.add("active");

      showMainContentLoader();
      fetch(pagePath, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "", // or send "user_info_id=" + encodeURIComponent(window.currentUserId) if you want
      })
        .then((res) => res.text())
        .then((html) => {
          document.getElementById("main-content").innerHTML = html;
          if (typeof initViewAccount === "function") initViewAccount();
        })
        .catch((err) => {
          console.error(err);
          Swal.fire("Error", "Error loading profile.", "error");
        });
      return; // Prevent the default GET loader from running
    }

    loadPage(pagePath);
  });

  document.addEventListener("DOMContentLoaded", function () {
    const initial = window.DEFAULT_PARTIAL || "partials/home/home_ad.php";
    setTimeout(() => {
      document.querySelectorAll(".nav-item").forEach((a) => {
        if (a.dataset.page === initial) a.classList.add("active");
      });
    }, 50);
    loadPage(initial);
  });

  window.loadPage = loadPage;

  // FORMATTED DATE
  function formatDateAvailed(dateStr) {
  if (!dateStr) return "";
  const dt = new Date(dateStr);
  if (isNaN(dt)) return dateStr; // fallback: if date parsing fails, show original
  // Format: November 15, 2025 - 08:23 PM
  const options = { year: "numeric", month: "long", day: "2-digit" };
  const datePart = dt.toLocaleDateString(undefined, options);
  let hours = dt.getHours();
  let mins = dt.getMinutes();
  let ampm = "AM";
  if (hours >= 12) { ampm = "PM"; if (hours > 12) hours -= 12; }
  if (hours == 0) hours = 12;
  return `${datePart} - ${hours.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")} ${ampm}`;
}

// FORMATTED DATE WITHOUT TIME
function formatDateDisplay(dateStr) {
  if (!dateStr) return "";
  const dt = new Date(dateStr);
  if (isNaN(dt)) return dateStr;
  // Output: November 15, 1991
  const options = { year: "numeric", month: "long", day: "2-digit" };
  return dt.toLocaleDateString(undefined, options);
}



  // ================== ADD ACCOUNT / PASSWORD TOGGLE ================== //
  window.togglePassword = function (fieldId, icon) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === "password") {
      input.type = "text";
      icon.classList.remove("fa-lock");
      icon.classList.add("fa-lock-open");
    } else {
      input.type = "password";
      icon.classList.remove("fa-lock-open");
      icon.classList.add("fa-lock");
    }
  };

  // ================== SERVICE MODALS ================== //

  let serviceViewHandlerAttached = false;
  function addServiceViewHandlerOnce() {
    if (serviceViewHandlerAttached) return;
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;
    mainContent.addEventListener("click", function (e) {
      const btn = e.target.closest(".view-service-btn");
      if (!btn) return;

      e.preventDefault();
      const serviceId = btn.getAttribute("data-id");

      showMainContentLoader();

      fetch("partials/services/view_service.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "service_id=" + encodeURIComponent(serviceId),
      })
        .then((res) => res.text())
        .then((html) => {
          mainContent.innerHTML = html;
          initServiceModals();
          // No need to rebind view button handler — this stays ONCE
        })
        .catch((err) => {
          console.error(err);
          alert("Error loading service details.");
        });
    });
    serviceViewHandlerAttached = true;
  }

  let archivedProcedureIds = [];
  function initServiceModals() {
    const addBtn = document.getElementById("addServiceBtn");
    const addModal = document.getElementById("addServiceModal");
    const addForm = document.getElementById("addServiceForm");

    function closeAddModal() {
      if (addModal) addModal.style.display = "none";
      if (addForm) addForm.reset(); // optional
    }
    window.closeAddModal = closeAddModal;

    if (addBtn && addModal && addForm) {
      addBtn.onclick = () => (addModal.style.display = "block");

      addForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const serviceCode = document
          .getElementById("service_code")
          ?.value.trim();
        const serviceName = document
          .getElementById("service_name")
          ?.value.trim();
        const roleId = document.getElementById("role_id")?.value;

        if (!serviceCode || !serviceName || !roleId) {
          Swal.fire("Error", "Please fill in all fields", "error");
          return;
        }

        const formData = new FormData(addForm);
        // send an AJAX flag so PHP knows this is an AJAX request
        formData.append("ajax_add_service", "1");

        fetch("partials/services/service.php", {
          // fetch the partial directly
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then((res) => {
            const ct = res.headers.get("content-type") || "";
            if (ct.indexOf("application/json") !== -1) return res.json();
            // server returned HTML (likely session/login or error page) — read text for debug
            return res.text().then((text) => {
              throw new Error("Server returned HTML: " + text);
            });
          })
          .then((data) => {
            if (data.status === "success") {
              // show success and update table without full reload
              Swal.fire({
                icon: "success",
                title: "Added",
                text: data.message,
                timer: 1600,
                showConfirmButton: false,
              });

              // add row to table (prepend so newest on top)
              const tableBody = document.getElementById("serviceTable");
              if (tableBody) {
                const newRow = document.createElement("tr");
                newRow.innerHTML = `
                  <td>${escapeHtml(data.service_code)}</td>
                  <td>${escapeHtml(data.service_name)}</td>
                  <td>
                    <button type="button" 
                      class="btn btn-outline btn-sm view-service-btn" 
                      data-id="${data.service_id}">
                      <i class="fas fa-eye"></i> View
                    </button>
                  </td>
                `;

                tableBody.prepend(newRow);

                if (typeof initViewServiceButtons === "function") {
                  initViewServiceButtons();
                }
              }

              // close modal and reset form
              addForm.reset();
              addModal.style.display = "none";
            } else {
              Swal.fire(
                "Error",
                data.message || "Failed to add service",
                "error"
              );
            }
          })
          .catch((err) => {
            // helpful debug popup + console log
            console.error("Add service AJAX error:", err);
            Swal.fire(
              "Error",
              "AJAX request failed (check console/network).",
              "error"
            );
          });
      });
    }

    // EDIT SERVICE INFORMATION
    // ---------- EDIT SERVICE ----------
    const editServiceBtn = document.getElementById("editServiceBtn");
    const editServiceModal = document.getElementById("editServiceModal");
    const editServiceForm = document.getElementById("editServiceForm");

    function closeEditServiceModal() {
      if (editServiceModal) editServiceModal.style.display = "none";
    }
    window.closeEditServiceModal = closeEditServiceModal;

    if (editServiceBtn && editServiceModal && editServiceForm) {
      // Open modal on button click
      editServiceBtn.onclick = () => (editServiceModal.style.display = "block");

      // Handle form submission
      editServiceForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const formData = new FormData(editServiceForm);
        const serviceId = document.getElementById("edit_service_id")?.value;

        showMainContentLoader();

        fetch("partials/services/view_service.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then((res) => {
            const ct = res.headers.get("content-type") || "";
            if (ct.indexOf("application/json") !== -1) return res.json();
            return res.text().then((text) => {
              throw new Error("Server returned HTML: " + text);
            });
          })
          .then((data) => {
            if (data.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Updated!",
                text: data.message,
                timer: 1600,
                showConfirmButton: false,
              });

              editServiceModal.style.display = "none";

              // ✅ Reload the service view to show updated info
              if (serviceId) {
                showMainContentLoader();
                fetch("partials/services/view_service.php", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                  },
                  body: "service_id=" + encodeURIComponent(serviceId),
                })
                  .then((res) => res.text())
                  .then((html) => {
                    const mainContent = document.getElementById("main-content");
                    if (mainContent) {
                      mainContent.innerHTML = html;
                      initServiceModals(); // Re-bind modals
                      initViewServiceButtons(); // Re-bind view buttons
                    }
                  })
                  .catch((err) => {
                    console.error(err);
                    Swal.fire(
                      "Error",
                      "Failed to reload service view.",
                      "error"
                    );
                  });
              }
            } else {
              Swal.fire("Error", data.message, "error");
            }
          })
          .catch((err) => {
            console.error(err);
            Swal.fire("Error", "Failed to update service.", "error");
          });
      });

      // Close modal when clicking outside
      window.addEventListener("click", (e) => {
        if (e.target === editServiceModal) closeEditServiceModal();
      });
    }

    // small helper to avoid injecting raw HTML
    function escapeHtml(str) {
      if (!str) return "";
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // ---------- ADD PROCEDURE ----------
    const addProcedureBtn = document.getElementById("addProcedureBtn");
    const addProcedureModal = document.getElementById("addProcedureModal");
    const procedureContainer = document.getElementById("procedureContainer");
    const addProcedureForm = document.getElementById("addProcedureForm");

    if (addProcedureBtn && addProcedureModal && procedureContainer) {
      // addProcedureBtn.onclick = () => {
      //   addProcedureModal.style.display = "block";
      //   procedureContainer.innerHTML = "";
      //   addSingleProcedure();
      //   console.log("✅ Procedure Modal Opened!");
      // };

      addProcedureBtn.onclick = function () {
        const serviceId = addProcedureForm.querySelector(
          "input[name='service_id']"
        ).value;
        openProcedureModal(serviceId); // new function that handles both add and edit
        // remove this console log later
        // console.log("✅ Procedure Modal opened, prefilled for edit/add!");
      };

      function closeProcedureModal() {
        addProcedureModal.style.display = "none";
      }
      window.closeProcedureModal = closeProcedureModal;

      function addSingleProcedure() {
        const template = document.getElementById("singleProcedureTemplate");
        if (!template) return;
        const clone = template.content.cloneNode(true);
        const closeBtn = clone.querySelector(".close");
        if (closeBtn) {
          closeBtn.addEventListener("click", function (e) {
            // debug message, remove after use
            // console.log("🔴 Close button clicked on addSingleProcedure");
            const box = e.target.closest(".procedure-box");
            // Single procedure: Look for ID in hidden input
            const idInput = box.querySelector("input[name='procedure_id[]']");
            // debug message, remove after use
            // console.log("🔍 Found ID input:", idInput);
            if (idInput && idInput.value) {
              // console.log("📌 Pushing ID to archive:", idInput.value);
              archivedProcedureIds.push(idInput.value);
              // console.log("📦 Archive array now:", archivedProcedureIds);
              idInput.parentNode.removeChild(idInput); // REMOVE from DOM
            }
            // Grouped procedure: Look for ID in hidden input
            const subIdInput = box.querySelector(
              "input[name^='sub_procedure_id']"
            );
            if (subIdInput && subIdInput.value) {
              // console.log("📌 Pushing sub ID to archive:", subIdInput.value);
              archivedProcedureIds.push(subIdInput.value);
              subIdInput.parentNode.removeChild(subIdInput); // REMOVE from DOM
            }
            box.remove();
          });
        }
        procedureContainer.appendChild(clone);
      }

      function addGroupBlock() {
        const groupTemplate = document.getElementById("groupTemplate");
        if (!groupTemplate) return;
        const groupWrapper = groupTemplate.content.cloneNode(true);
        const groupBox = groupWrapper.querySelector(".group-box");
        const closeGroup = groupWrapper.querySelector(".close-group");
        const addProcBtnInside = groupWrapper.querySelector(
          ".addProcedureBtnInside"
        );
        const procList = groupWrapper.querySelector(".procedure-list");

        closeGroup.addEventListener("click", function () {
          const ids = groupBox.querySelectorAll(
            "input[name^='sub_procedure_id']"
          );
          ids.forEach((input) => {
            if (input.value) {
              archivedProcedureIds.push(input.value);
              input.parentNode.removeChild(input); // REMOVE from DOM
            }
          });
          groupBox.remove();
          reindexAllGroupIndices();
        });

        procedureContainer.appendChild(groupWrapper);

        function getCurrentIndex() {
          return Array.from(document.querySelectorAll(".group-box")).indexOf(
            groupBox
          );
        }

        addProcedureBlock(procList, getCurrentIndex());
        addProcBtnInside.addEventListener("click", () =>
          addProcedureBlock(procList, getCurrentIndex())
        );
      }

      // new modal open function for both add and edit
      function openProcedureModal(serviceId) {
        archivedProcedureIds = []; // Reset archive list on open
        addProcedureModal.style.display = "block";
        procedureContainer.innerHTML = "";

        fetch("partials/services/view_service.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body:
            "service_id=" +
            encodeURIComponent(serviceId) +
            "&action=get_procedures",
        })
          .then((res) => res.json())
          .then((data) => {
            procedureContainer.innerHTML = ""; //quick fix for duplication problems

            if (data.singles) {
              data.singles.forEach((proc) => {
                addSingleProcedurePrefilled(proc);
              });
            }
            if (data.groups) {
              data.groups.forEach((group) => {
                addGroupBlockPrefilled(group);
              });
            }
          })
          .catch(() => {
            addSingleProcedure(); // fallback to blank if error
          });
      }

      function addSingleProcedurePrefilled(proc) {
        const template = document.getElementById("singleProcedureTemplate");
        if (!template) return;
        const clone = template.content.cloneNode(true);
        clone.querySelector("input[name='procedure_name[]']").value = proc.name;
        clone.querySelector("input[name='procedure_price[]']").value =
          proc.price;
        // Append a hidden input for ID if editing
        const idInput = document.createElement("input");
        idInput.type = "hidden";
        idInput.name = "procedure_id[]";
        idInput.value = proc.id;
        const procedureContent = clone.querySelector(".procedure-content");
        procedureContent.appendChild(idInput); // <-- Put it INSIDE procedure-content

        // Attach close button with archive logic
        const closeBtn = clone.querySelector(".close");
        if (closeBtn) {
          closeBtn.addEventListener("click", function (e) {
            // console.log(
            //   "🔴 Close button clicked on addSingleProcedurePrefilled"
            // );
            const box = e.target.closest(".procedure-box");
            const idInput = box.querySelector("input[name='procedure_id[]']");
            // console.log("🔍 Found ID input:", idInput);
            if (idInput && idInput.value) {
              // console.log("📌 Pushing ID to archive:", idInput.value);
              archivedProcedureIds.push(idInput.value);
              // console.log("📦 Archive array now:", archivedProcedureIds);
              idInput.parentNode.removeChild(idInput); // REMOVE from DOM
            }
            const subIdInput = box.querySelector(
              "input[name^='sub_procedure_id']"
            );
            if (subIdInput && subIdInput.value) {
              // console.log("📌 Pushing sub ID to archive:", subIdInput.value);
              archivedProcedureIds.push(subIdInput.value);
              subIdInput.parentNode.removeChild(subIdInput); // REMOVE from DOM
            }
            box.remove();
          });
        }

        procedureContainer.appendChild(clone);
      }

      function addGroupBlockPrefilled(group) {
  const groupTemplate = document.getElementById("groupTemplate");
  if (!groupTemplate) return;
  const groupWrapper = groupTemplate.content.cloneNode(true);
  const groupBox = groupWrapper.querySelector(".group-box");
  const addProcBtnInside = groupWrapper.querySelector(".addProcedureBtnInside");
  const closeGroup = groupWrapper.querySelector(".close-group");
  const procList = groupWrapper.querySelector(".procedure-list");

  groupWrapper.querySelector("input[name='group_name[]']").value = group.groupName;

  const groupIdInput = document.createElement("input");
  groupIdInput.type = "hidden";
  groupIdInput.name = "group_id[]";
  groupIdInput.value = group.groupId;
  const groupContent = groupWrapper.querySelector(".group-content");
  groupContent.appendChild(groupIdInput);

  // ✅ Append to container FIRST
  procedureContainer.appendChild(groupWrapper);

  // ✅ Get current index AFTER appending
  function getCurrentIndex() {
    return Array.from(document.querySelectorAll(".group-box")).indexOf(groupBox);
  }

  // Add existing procedures with PROPER indexed names
  if (group.procs && Array.isArray(group.procs)) {
    group.procs.forEach((proc) => {
      const procTemplate = document.getElementById("groupProcedureTemplate");
      if (!procTemplate) return;
      const procWrapper = procTemplate.content.cloneNode(true);

      const gIdx = getCurrentIndex();

      const nameInput = procWrapper.querySelector("input[name^='sub_procedure_name']");
      nameInput.name = `sub_procedure_name[${gIdx}][]`;
      nameInput.value = proc.name;

      const priceInput = procWrapper.querySelector("input[name^='sub_procedure_price']");
      priceInput.name = `sub_procedure_price[${gIdx}][]`;
      priceInput.value = proc.price;

      // ✅ CRITICAL: Add the ID as a hidden input WITH PROPER INDEXING
      const procIdInput = document.createElement("input");
      procIdInput.type = "hidden";
      procIdInput.name = `sub_procedure_id[${gIdx}][]`;
      procIdInput.value = proc.id;
      const procedureContent = procWrapper.querySelector(".procedure-content");
      procedureContent.appendChild(procIdInput);

      const closeBtn = procWrapper.querySelector(".close");
      if (closeBtn) {
        closeBtn.addEventListener("click", function (e) {
          const box = e.target.closest(".procedure-box");
          const subIdInput = box.querySelector("input[name^='sub_procedure_id']");
          if (subIdInput && subIdInput.value) {
            // ✅ Add to archive list
            archivedProcedureIds.push(subIdInput.value);
            console.log("🗑️ Archived procedure ID:", subIdInput.value); // ✅ Debug
          }
          box.remove();
          // ✅ DON'T reindex immediately - wait until form submit
          // reindexAllGroupIndices(); // ❌ REMOVE THIS
        });
      }

      procList.appendChild(procWrapper);
    });
  }

  // Wire up the "+ Add Procedure" button
  if (addProcBtnInside) {
    addProcBtnInside.addEventListener("click", () => {
      addProcedureBlock(procList, getCurrentIndex());
    });
  }

  // Group close: archive all procedures in group
  if (closeGroup) {
    closeGroup.addEventListener("click", function () {
      const ids = groupBox.querySelectorAll("input[name^='sub_procedure_id']");
      ids.forEach((input) => {
        if (input.value) {
          archivedProcedureIds.push(input.value);
          console.log("🗑️ Archived group procedure ID:", input.value); // ✅ Debug
        }
      });
      groupBox.remove();
      // ✅ Reindex after removing entire group
      reindexAllGroupIndices();
    });
  }
}

      function addProcedureBlock(procList, groupIndex) {
        const groupProcedureTemplate = document.getElementById(
          "groupProcedureTemplate"
        );
        if (!groupProcedureTemplate) return;
        const procWrapper = groupProcedureTemplate.content.cloneNode(true);
        procWrapper.querySelectorAll("input").forEach((input) => {
          if (input.name.includes("sub_procedure_name"))
            input.name = `sub_procedure_name[${groupIndex}][]`;
          if (input.name.includes("sub_procedure_price"))
            input.name = `sub_procedure_price[${groupIndex}][]`;
        });
        const closeBtn = procWrapper.querySelector(".close");
        if (closeBtn)
          closeBtn.addEventListener("click", (e) => {
            e.target.closest(".procedure-box").remove();
            reindexAllGroupIndices();
          });
        procList.appendChild(procWrapper);
      }

      function reindexAllGroupIndices() {
  const groups = Array.from(document.querySelectorAll(".group-box"));
  groups.forEach((groupBox, newIndex) => {
    const procBoxes = groupBox.querySelectorAll(".procedure-box");
    procBoxes.forEach((procBox) => {
      procBox.querySelectorAll("input").forEach((input) => {
        // ✅ ONLY update the index in the name, NOT the entire name
        if (input.name && input.name.includes("sub_procedure_name")) {
          // Extract current index from name like sub_procedure_name[3][]
          input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
        }
        if (input.name && input.name.includes("sub_procedure_price")) {
          input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
        }
        if (input.name && input.name.includes("sub_procedure_id")) {
          // ✅ CRITICAL: Preserve the ID input name structure
          input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
        }
      });
    });
  });
}

      window.addSingleProcedure = addSingleProcedure;
      window.addGroupBlock = addGroupBlock;

      window.addEventListener("click", (e) => {
        if (e.target === addProcedureModal)
          addProcedureModal.style.display = "none";
      });

      if (addProcedureForm) {
  addProcedureForm.addEventListener("submit", (e) => {
    e.preventDefault();
    
    // ✅ CRITICAL: Reindex ONLY when submitting
    reindexAllGroupIndices();

    console.log("📤 Submitting with archived IDs:", archivedProcedureIds); // ✅ Debug

    // Remove old archive input
    const oldInput = addProcedureForm.querySelector("input[name='archived_procedure_ids']");
    if (oldInput) oldInput.remove();

    // Add archived IDs
    const archiveInput = document.createElement("input");
    archiveInput.type = "hidden";
    archiveInput.name = "archived_procedure_ids";
    archiveInput.value = JSON.stringify(archivedProcedureIds);
    addProcedureForm.appendChild(archiveInput);

    const formData = new FormData(addProcedureForm);
    formData.append("action", "add_procedure");

    // ✅ Debug: Log what's being sent
    console.log("📋 Form Data:");
    for (let [key, value] of formData.entries()) {
      console.log(key, value);
    }

    fetch("partials/services/view_service.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then((html) => {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = html;

        const newList = tempDiv.querySelector("#procedureListContainer");
        const currentList = document.querySelector("#procedureListContainer");

        if (newList && currentList) {
          currentList.replaceWith(newList);
        }

        addProcedureModal.style.display = "none";
        addProcedureForm.reset();
        archivedProcedureIds = [];

        Swal.fire({
          icon: "success",
          title: "Saved!",
          text: "Procedures updated successfully.",
          timer: 1800,
          showConfirmButton: false,
        });
      })
      .catch((err) => {
        console.error(err);
        Swal.fire("Error", "Failed to save procedures.", "error");
      });
  });
}
    }

      // ARCHIVE SERVICE BUTTON
      const archiveBtn = document.getElementById("archiveServiceBtn");
      if (archiveBtn) {
        archiveBtn.onclick = function () {
          const serviceId = document.getElementById("edit_service_id")?.value || document.querySelector("input[name='service_id']")?.value;
          if (!serviceId) {
            Swal.fire("Error", "No service selected.");
            return;
          }
          Swal.fire({
            title: "Are you sure?",
            text: "This will action will remove the service",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, archive"
          }).then((result) => {
            if (result.isConfirmed) {
              fetch("partials/services/view_service.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=archive_service&service_id=" + encodeURIComponent(serviceId)
              })
                .then(res => res.json())
                .then(data => {
                  if (data.status === "success") {
                   Swal.fire("Archived!", data.message, "success").then(() => {
                    fetch("partials/services/service.php")
                      .then(response => response.text())
                      .then(html => {
                        document.getElementById("main-content").innerHTML = html;
                        if (typeof addServiceViewHandlerOnce === "function") addServiceViewHandlerOnce();
                      });
                  });

                  } else {
                    Swal.fire("Error", data.message, "error");
                  }
                });
            }
          });
        };
      }

      // RESTORE SERVICE BUTTON
      const restoreBtn = document.getElementById("restoreServiceBtn");
      if (restoreBtn) {
        restoreBtn.onclick = function () {
          const serviceId = document.getElementById("edit_service_id")?.value || document.querySelector("input[name='service_id']")?.value;
          if (!serviceId) {
            Swal.fire("Error", "No service selected.");
            return;
          }
          Swal.fire({
            title: "Restore Service?",
            text: "This will make the service available again.",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Yes, restore"
          }).then((result) => {
            if (result.isConfirmed) {
              fetch("partials/services/view_service.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=restore_service&service_id=" + encodeURIComponent(serviceId)
              })
                .then(res => res.json())
                .then(data => {
                  if (data.status === "success") {
                    Swal.fire("Restored!", data.message, "success").then(() => location.reload());
                  } else {
                    Swal.fire("Error", data.message, "error");
                  }
                });
            }
          });
        };
      }

  }

  // ====================VIEW SERVICE BUTTON========================
  function initViewServiceButtons() {
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;

    // mainContent.addEventListener("click", function (e) {
    //   const btn = e.target.closest(".view-service-btn");
    //   if (!btn) return;

    //   e.preventDefault();
    //   const serviceId = btn.getAttribute("data-id");

    //   showMainContentLoader();

    //   fetch("partials/services/view_service.php", {
    //     method: "POST",
    //     headers: { "Content-Type": "application/x-www-form-urlencoded" },
    //     body: "service_id=" + encodeURIComponent(serviceId),
    //   })
    //     .then((res) => res.text())
    //     .then((html) => {
    //       mainContent.innerHTML = html;
    //       initServiceModals();
    //       initViewServiceButtons(); // rebind after load
    //     })
    //     .catch((err) => {
    //       console.error(err);
    //       alert("Error loading service details.");
    //     });
    // });
  }

  window.initViewServiceButtons = initViewServiceButtons;



  // ========== APPLY FILTERS WITH AJAX ACCOUNTS(INSTANT UPDATE) ==========
  function applyFiltersWithAjax() {
    const status = document.getElementById("statusFilter")?.value || "active";
    const sort = document.getElementById("sortFilter")?.value || "date_desc";
    const search = document.getElementById("searchLive")?.value || "";
    const tableBody = document.getElementById("accountsTable");

    // Show loading state (optional)
    // const tableBody = document.getElementById("accountsTable");
    // if (tableBody) {
    //   tableBody.style.opacity = "1.5";
    // }

    // showTableLoader("#accountsTable", 5);

    $.ajax({
      url: "partials/accounts/search_acc.php",
      method: "POST",
      data: {
        search: search,
        status: status,
        sort: sort,
      },
      success: function (response) {
        $("#accountsTable").html(response);
        // Remove loading state
        if (tableBody) {
          tableBody.style.opacity = "1";
        }

        // Re-initialize view buttons
        if (typeof initViewAccount === "function") {
          initViewAccount();
        }
      },
      error: function (err) {
        console.error(err);
        Swal.fire("Error", "Failed to apply filters.", "error");

        // Remove loading state even on error
        // if (tableBody) {
        //   tableBody.style.opacity = "1";
        // }
      },
    });
  }

  // ======ONE TIME FILTER/SEARCH EVENT BINDING FOR ACCOUNTS==========
  // --- One-time filter/search event binding for Accounts ---
  function bindAccountFilterEvents() {
    const statusFilter = document.getElementById("statusFilter");
    const sortFilter = document.getElementById("sortFilter");
    const searchInput = document.getElementById("searchLive");

    if (searchInput) searchInput.onkeyup = applyFiltersWithAjax;
    if (statusFilter) statusFilter.onchange = applyFiltersWithAjax;
    if (sortFilter) sortFilter.onchange = applyFiltersWithAjax;
  }

  // Call this ONLY ONCE per page load, or after main filter HTML is inserted (but NOT after every table/AJAX update)
  window.addEventListener("DOMContentLoaded", bindAccountFilterEvents);

  // ==========view account click handler for less flooding requests=========
  let accountViewHandlerAttached = false;

  function addAccountViewHandlerOnce() {
    if (accountViewHandlerAttached) return;
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;
    mainContent.addEventListener("click", function (e) {
      const btn = e.target.closest(".view-account-btn");
      if (!btn) return;

      e.preventDefault();
      const userId = btn.getAttribute("data-user_info_id");

      showMainContentLoader();

      fetch("partials/accounts/view_account.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "user_info_id=" + encodeURIComponent(userId),
      })
        .then((res) => res.text())
        .then((html) => {
          mainContent.innerHTML = html;
          // If you want to support nested modals/views, call initViewAccount() here
          initViewAccount();
        })
        .catch((err) => {
          console.error(err);
          alert("Error loading account details.");
        });
    });
    accountViewHandlerAttached = true;
  }

  // =========VIEW ACCCOUNT MODIFIED BETTER UX RELOAD FOR CHANGING FILTERS==========
  window.closeModal = function () {
    const modal = document.getElementById("editModal");
    if (modal) modal.style.display = "none";
  };
  // ================== VIEW ACCOUNT / EDIT MODAL ==================
  function initViewAccount() {
    // ---------- VIEW ACCOUNT BUTTONS ----------
    const mainContent = document.getElementById("main-content");

    function closeAccountEditModal() {
      const modal = document.getElementById("editModal");
      if (modal) modal.style.display = "none";
    }

    // ---------- EDIT MODAL ----------
    const editBtn = document.getElementById("editBtn");
    const modal = document.getElementById("editModal");
    const form = document.getElementById("editForm");
    const closeBtn = document.getElementById("closeBtnEdit");

    if (editBtn && modal) {
      editBtn.onclick = () => {
        modal.style.display = "block";
        const firstInput = modal.querySelector("input, select");
        if (firstInput) firstInput.focus();
      };
    }

    if (closeBtn) closeBtn.onclick = closeAccountEditModal;

    if (modal) {
      modal.onclick = function (e) {
        if (e.target === modal) closeAccountEditModal();
      };
    }

    window.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal && modal.style.display === "block")
        closeAccountEditModal();
    });

    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        const firstName = document.getElementById("firstName")?.value.trim();
        const middleName = document.getElementById("middleName")?.value.trim();
        const lastName = document.getElementById("lastName")?.value.trim();
        const sex = document.getElementById("sex")?.value;
        const dob = document.getElementById("dob")?.value;
        const phone = document.getElementById("phone")?.value.trim();
        const address = document.getElementById("address")?.value.trim();

        if (!firstName || !lastName || !sex || !dob) {
          alert("Please fill out all required fields.");
          return;
        }
        const today = new Date().toISOString().split("T")[0];
        if (dob > today) {
          alert("Date of Birth cannot be in the future.");
          return;
        }
        if (!isAtLeast18YearsOld(dob)) {
          alert("User must be at least 18 years old.");
          return;
        }
        if (phone && !/^[0-9]{11}$/.test(phone)) {
          alert("Phone number must be 11 digits.");
          return;
        }
        if (address && address.length > 255) {
          alert("Address is too long.");
          return;
        }
        const namePattern = /^[A-Za-z\s\-]+$/;
        if (!namePattern.test(firstName) || !namePattern.test(lastName)) {
          alert(
            "First and Last names can only contain letters, spaces, and hyphens."
          );
          return;
        }

        const formData = new FormData(form);

        fetch("partials/accounts/view_account.php", {
          method: "POST",
          body: formData,
        })
          .then((res) => res.text())
          .then(() => {
            const userId = document.getElementById("userId")?.value;
            if (!userId) return;

            fetch("partials/accounts/view_account.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "user_info_id=" + encodeURIComponent(userId),
            })
              .then((res) => res.text())
              .then((html) => {
                mainContent.innerHTML = html;
                initViewAccount();
                Swal.fire({
                  icon: "success",
                  title: "Saved!",
                  text: "Changes have been saved successfully.",
                  timer: 1800,
                  showConfirmButton: false,
                });
              })
              .catch((err) => {
                console.error(err);
                Swal.fire({
                  icon: "error",
                  title: "Error!",
                  text: "Error reloading account!",
                  showConfirmButton: false,
                });
              });
          })
          .catch((err) => {
            console.error(err);
            Swal.fire({
              icon: "error",
              title: "Oops!",
              text: "Error saving changes!",
              showConfirmButton: false,
            });
          });
      });
    }

    window.onclick = function (e) {
      if (e.target === modal) closeModal();
    };

    // ---------- ARCHIVE ACCOUNT ----------
    document.querySelectorAll(".archiveAccountForm").forEach((form) => {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const userId = form.querySelector('input[name="user_info_id"]').value;
        const isRestore = form.querySelector('button[name="restore_account"]');

        const actionText = isRestore ? "restore" : "archive";
        const actionTextCap = isRestore ? "Restore" : "Archive";

        Swal.fire({
          title: "Are you sure?",
          text: `This account will be ${actionText}d!`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: `Yes, ${actionText} it!`,
        }).then((result) => {
          if (result.isConfirmed) {
            const formData = new FormData();
            if (isRestore) {
              formData.append("restore_account", "1");
            } else {
              formData.append("archive_account", "1");
            }
            formData.append("user_info_id", userId);

            fetch("partials/accounts/view_account.php", {
              method: "POST",
              body: formData,
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.status === "success") {
                  Swal.fire(`${actionTextCap}d!`, data.message, "success");
                  // Reload accounts table
                  if (typeof loadPage === "function") {
                    loadPage("partials/accounts/accounts.php");
                  }
                } else {
                  Swal.fire("Error", data.message, "error");
                }
              })
              .catch((err) => {
                console.error(err);
                Swal.fire("Error", `Failed to ${actionText} account.`, "error");
              });
          }
        });
      });
    });

    // ===========EDIT ACCCOUNT MODAL==========
    function initAccountSettingsModal() {
      const btn = document.getElementById("editAccountBtn");
      const modal = document.getElementById("editAccountModal");
      const closeBtn = document.getElementById("closeEditAccountModalBtn");
      const form = document.getElementById("editAccountForm");

      if (btn && modal)
        btn.onclick = () => {
          modal.style.display = "block";
        };
      if (closeBtn && modal)
        closeBtn.onclick = () => {
          modal.style.display = "none";
        };
      if (modal) {
        modal.onclick = function (e) {
          if (e.target === modal) modal.style.display = "none";
        };
      }
      window.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal && modal.style.display === "block")
          modal.style.display = "none";
      });

      if (form) {
        form.onsubmit = function (e) {
          e.preventDefault();
          const fd = new FormData(form);
          fd.append("change_account_credentials", "1");
          fetch("partials/accounts/view_account.php", {
            method: "POST",
            body: fd,
          })
            .then((res) => res.json())
            .then((data) => {
              if (data.status === "success") {
                modal.style.display = "none";
                form.reset();
                Swal.fire("Success", data.message, "success");
                modal.style.display = "none";
              } else {
                modal.style.display = "none";
                form.reset();
                Swal.fire("Error", data.message, "error");
              }
            });
        };
      }
    }

    // In your main view account JS, after updating main content:
    initAccountSettingsModal();
  }

  // ==========ADD ACCOUNT PHP==========

  // ==========ADD ACCOUNT PHP==========
function initAddAccount() {
  const form = document.getElementById("addAccountForm");
  const cancelBtn = document.getElementById("cancelAddAccount");

  if (!form) return;

  // ---------- CANCEL BUTTON ----------
  if (cancelBtn) {
    cancelBtn.onclick = (e) => {
      e.preventDefault();
      if (typeof loadPage === "function") {
        loadPage("partials/accounts/accounts.php");
      }
    };
  }

  // ---------- SUBMIT FORM ----------
  form.addEventListener("submit", (e) => {
    e.preventDefault();

    // Client-side validation
    const firstName = form.querySelector('[name="firstname"]')?.value.trim();
    const lastName = form.querySelector('[name="lastname"]')?.value.trim();
    const sex = form.querySelector('[name="sex"]')?.value;
    const dob = form.querySelector('[name="dob"]')?.value;
    const phone = form.querySelector('[name="phone"]')?.value.trim();
    const username = form.querySelector('[name="username"]')?.value.trim();
    const password = form.querySelector('[name="password"]')?.value;
    const confirmPassword = form.querySelector('[name="confirm_password"]')?.value;
    const role = form.querySelector('[name="role"]')?.value;

    // Basic validation
    if (!firstName || !lastName || !sex || !dob || !phone || !username || !password || !role) {
      Swal.fire("Warning", "Please fill in all required fields.", "warning");
      return;
    }

    // Name validation
    const namePattern = /^[A-Za-z\s\-\.]+$/;
    if (!namePattern.test(firstName) || !namePattern.test(lastName)) {
      Swal.fire("Warning", "Names can only contain letters, spaces, hyphens, and periods.", "warning");
      return;
    }

    // Age validation
    if (!window.isAtLeast18YearsOld(dob)) {
      Swal.fire("Warning", "User must be at least 18 years old!", "warning");
      return;
    }

    // Future date check
    const today = new Date().toISOString().split('T')[0];
    if (dob > today) {
      Swal.fire("Warning", "Date of birth cannot be in the future.", "warning");
      return;
    }

    // Phone validation
    if (!/^\d{11}$/.test(phone)) {
      Swal.fire("Warning", "Phone number must be exactly 11 digits.", "warning");
      return;
    }

    // Password match
    if (password !== confirmPassword) {
      Swal.fire("Warning", "Passwords do not match!", "warning");
      return;
    }

    // Password length
    if (password.length < 8) {
      Swal.fire("Warning", "Password must be at least 8 characters long.", "warning");
      return;
    }

    // Username length
    if (username.length < 8 || username.length > 20) {
      Swal.fire("Warning", "Username must be between 8-20 characters.", "warning");
      return;
    }

    const formData = new FormData(form);

    // Show loading
    Swal.fire({
      title: 'Creating Account...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    fetch("partials/accounts/add_account.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.status === 'success') {
          Swal.fire({
            icon: "success",
            title: "Account Added",
            text: data.message,
          }).then(() => {
            if (typeof loadPage === "function") {
              loadPage("partials/accounts/accounts.php");
            }
          });
        } else {
          Swal.fire({
            icon: data.status,
            title: data.status === 'warning' ? 'Validation Error' : 'Error',
            text: data.message,
          });
        }
      })
      .catch((err) => {
        console.error(err);
        Swal.fire("Error", "Failed to add account. Please try again.", "error");
      });
  });
}

// ==========add acc button to view=========
function initAddAccountButton() {
  const mainContent = document.getElementById("main-content");
  const addBtn = document.getElementById("addAccountBtn");
  if (!addBtn || !mainContent) return;

  addBtn.addEventListener("click", (e) => {
    e.preventDefault();
    showMainContentLoader();

    fetch("partials/accounts/add_account.php")
      .then((res) => res.text())
      .then((html) => {
        mainContent.innerHTML = html;
        if (typeof initAddAccount === "function") initAddAccount();
      })
      .catch((err) => {
        console.error(err);
        Swal.fire("Error", "Failed to load Add Account form.", "error");
      });
  });
}

// Expose globally
window.initAddAccount = initAddAccount;

  // ===========ADMINISTRATOR DASHBOARD ===========

  // ============Bind Admin Table Events VERSION 1===========
  // function bindAdminTableEvents() {
  //     const searchInput = document.getElementById('adminSearchPatient');
  //     const statusFilter = document.getElementById('adminAvailStatusFilter');
  //     const sortFilter = document.getElementById('adminPatientSortFilter');
  //     const tableBody = document.getElementById('adminPatientsTable');

  //     if (!searchInput || !statusFilter || !sortFilter || !tableBody) {
  //         console.error("Admin dashboard elements not found—check HTML IDs!");
  //         return;
  //     }

  //     function applyAdminFiltersAjax() {
  //         const search = searchInput.value || '';
  //         const status = statusFilter.value || 'all';
  //         const sort = sortFilter.value || 'date_desc';
  //         tableBody.style.opacity = '0.5';

  //         $.ajax({
  //             url: "partials/home/search_avails.php",
  //             method: "POST",
  //             data: { search, availStatus: status, sort },
  //             success: function(resp) {
  //                 tableBody.innerHTML = resp;
  //                 tableBody.style.opacity = '1';
  //                 initAdminModalHandlers();
  //             },
  //             error: function(err) {
  //                 tableBody.style.opacity = '1';
  //                 Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
  //             }
  //         });
  //     }

  //     searchInput.addEventListener('keyup', applyAdminFiltersAjax);
  //     statusFilter.addEventListener('change', applyAdminFiltersAjax);
  //     sortFilter.addEventListener('change', applyAdminFiltersAjax);

  //     applyAdminFiltersAjax();
  // }

  // ============Bind Admin Table Events VERSION 2===========
  function bindAdminTableEvents() {
    const statusDropdown = document.getElementById("adminStatusDropdown");
    const datePreset = document.getElementById("adminDatePreset");
    const dateFrom = document.getElementById("adminDateFrom");
    const dateTo = document.getElementById("adminDateTo");
    const dateToLabel = document.getElementById("adminDateToLabel");
    const searchInput = document.getElementById("adminSearchPatient");
    const sortFilter = document.getElementById("adminPatientSortFilter");
    const tableBody = document.getElementById("adminPatientsTable");

    function applyAdminFiltersAjax() {
      // Get status array (default to all if none checked for robustness):
      // let statusArr = Array.from(document.querySelectorAll('.adminStatusCheckbox:checked')).map(cb => cb.value);
      // if (statusArr.length === 0) statusArr = ['Pending', 'Completed', 'Canceled'];

      let statusArr = [];
      const statusVal = statusDropdown.value;

      if (statusVal === "all") {
        statusArr = ["Pending", "Completed", "Canceled"];
      } else {
        statusArr = [statusVal];
      }

      // Returns 'YYYY-MM-DD' in browser's local time
      function getLocalDateString(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, "0");
        const dd = String(dateObj.getDate()).padStart(2, "0");
        return `${yyyy}-${mm}-${dd}`;
      }

      // Get date range:
      let filterFrom = "";
      let filterTo = "";
      const now = new Date();
      const preset = datePreset.value;
      if (preset === "today") {
        filterFrom = filterTo = getLocalDateString(now);
      } else if (preset === "yesterday") {
        const d = new Date(now);
        d.setDate(d.getDate() - 1);
        filterFrom = filterTo = getLocalDateString(d);
      } else if (preset === "last7") {
        const d = new Date(now);
        d.setDate(d.getDate() - 6);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "last30") {
        const d = new Date(now);
        d.setDate(d.getDate() - 29);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "custom") {
        filterFrom = dateFrom.value;
        filterTo = dateTo.value;
      }

      // // Get date range
      // let filterFrom = '', filterTo = '';
      // const now = new Date();
      // switch (datePreset.value) {
      //   case 'today':
      //     filterFrom = filterTo = now.toISOString().slice(0, 10); break;
      //   case 'yesterday':
      //     const yest = new Date(now); yest.setDate(yest.getDate() - 1);
      //     filterFrom = filterTo = yest.toISOString().slice(0, 10); break;
      //   case 'last7':
      //     const d7 = new Date(now); d7.setDate(d7.getDate() - 6);
      //     filterFrom = d7.toISOString().slice(0, 10); filterTo = now.toISOString().slice(0, 10); break;
      //   case 'last30':
      //     const d30 = new Date(now); d30.setDate(d30.getDate() - 29);
      //     filterFrom = d30.toISOString().slice(0, 10); filterTo = now.toISOString().slice(0, 10); break;
      //   case 'custom':
      //     filterFrom = dateFrom.value; filterTo = dateTo.value; break;
      // }

      const search = searchInput.value || "";
      const sort = sortFilter.value || "date_desc";
      tableBody.style.opacity = "0.5";

      $.ajax({
        url: "partials/home/search_avails.php",
        method: "POST",
        data: {
          statusArr,
          dateFrom: filterFrom,
          dateTo: filterTo,
          search,
          sort,
        },
        success: function (resp) {
          tableBody.innerHTML = resp;
          tableBody.style.opacity = "1";
          initAdminModalHandlers();
        },
        error: function (err) {
          tableBody.style.opacity = "1";
          Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
        },
      });
    }

    // UI show/hide for custom range
    // datePreset.addEventListener('change', function () {
    //   if (this.value === 'custom') {
    //     dateFrom.style.display = '';
    //     dateTo.style.display = '';
    //     dateToLabel.style.display = '';
    //   } else {
    //     dateFrom.style.display = 'none';
    //     dateTo.style.display = 'none';
    //     dateToLabel.style.display = 'none';
    //     dateFrom.value = '';
    //     dateTo.value = '';
    //   }
    //   applyAdminFiltersAjax();
    // });

    datePreset.addEventListener("change", function () {
      const customDate = document.querySelector(".custom-date");
      if (this.value === "custom") {
        customDate.style.display = "flex";
      } else {
        customDate.style.display = "none";
        dateFrom.value = "";
        dateTo.value = "";
      }
      applyAdminFiltersAjax();
    });

    // Bind other filter events
    statusDropdown.addEventListener("change", applyAdminFiltersAjax);
    dateFrom.addEventListener("change", applyAdminFiltersAjax);
    dateTo.addEventListener("change", applyAdminFiltersAjax);
    searchInput.addEventListener("keyup", applyAdminFiltersAjax);
    sortFilter.addEventListener("change", applyAdminFiltersAjax);

    // Initial
    applyAdminFiltersAjax();
  }

  function initAdminModalHandlers() {
    const modal = document.getElementById("viewAdminAvailModal");
    const closeBtn = document.getElementById("adminCloseAvailModalBtn");
    const proceduresGrid = document.getElementById("adminModalProceduresGrid");
    const table = document.getElementById("adminPatientsTable");
    if (!modal || !closeBtn || !proceduresGrid || !table) return;

    table.onclick = function (e) {
      const btn = e.target.closest(".view-avail-btn");
      if (!btn) return;
      const avail_id = btn.getAttribute("data-avail_id");
      const fd = new FormData();
      fd.append("ajax_get_avail_info", "1");
      fd.append("avail_id", avail_id);
      fetch("partials/home/search_avails.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            fillAdminModal(
              data.service, 
              data.procedures,
              data.patient,
              data.billing
            );
            modal.style.display = "block";
            closeBtn.focus();
          } else {
            Swal && Swal.fire("Error", data.message, "error");
          }
        });
    };
    closeBtn.onclick = closeAdminModal;
    modal.onclick = function (e) {
      if (e.target === modal) closeAdminModal();
    };
    window.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.style.display === "block")
        closeAdminModal();
    });
    function closeAdminModal() {
      modal.style.display = "none";
      document.getElementById("adminModalServiceName").innerText = "";
      document.getElementById("adminModalRequestedBy").innerText = "";
      document.getElementById("adminModalDateAvailed").innerText = "";
      document.getElementById("adminModalCaseNo").innerText = "";
      document.getElementById("adminModalPackageName").innerText = ""; 
      document.getElementById("adminModalBriefHistory").innerText = "";
      document.getElementById("adminModalStatus").innerText = "";
       document.getElementById("adminModalBillingStatus").innerText = "";
      proceduresGrid.innerHTML = "";
    }
    function fillAdminModal(service, procedures, patient, billing) {

      // SERVICE DETAILS
      document.getElementById("adminModalServiceName").innerText =
        service.service_name || "";
      document.getElementById("adminModalRequestedBy").innerText =
        service.requested_by || "--";
      document.getElementById("adminModalDateAvailed").innerText =
        formatDateAvailed(service.date_availed);
      document.getElementById("adminModalCaseNo").innerText =
        service.case_no || "";
        document.getElementById("adminModalPackageName").innerText =
    service.package_name || "--";
      document.getElementById("adminModalBriefHistory").innerText =
        service.brief_history || "--";
      document.getElementById("adminModalStatus").innerText =
        service.status || "";
        document.getElementById("adminModalBillingStatus").innerText =
    service.billing_status || "--";
      fillAdminProceduresGrid(procedures);

      // PATIENT DETAILS
        // Patient Details (optional, shown if patient exists)
      if (patient) {
        document.getElementById("adminModalPatientName").innerText =
          patient.name || "";
        document.getElementById("adminModalPatientDOB").innerText =
          formatDateDisplay(patient.dob);
        document.getElementById("adminModalPatientSex").innerText =
          patient.sex || "";
        document.getElementById("adminModalPatientPhone").innerText =
          patient.phone || "";
      }

      // billing details
      if (billing) {
        document.getElementById("adminModalOR").innerText =
          billing.or_number || "";
        document.getElementById("adminModalSubtotal").innerText =
          billing.amount_total || "";
        // Discount
        let discount = "";
        if (billing.discount_name) {
          discount =
            billing.discount_name +
            (billing.discount_value ? ` (${billing.discount_value}%)` : "");
        } else if (billing.custom_discount_value) {
          discount = `Custom (${billing.custom_discount_value}%)`;
        } else {
          discount = "None";
        }
        document.getElementById("adminModalDiscount").innerText = discount;
        document.getElementById("adminModalTotal").innerText =
          billing.discount_amount || billing.amount_total || "";
      }

    }


    function fillAdminProceduresGrid(arr) {
  const proceduresGrid = document.getElementById("adminModalProceduresGrid");
  proceduresGrid.innerHTML = "";
  if (!arr || arr.length === 0) {
    proceduresGrid.innerHTML =
      '<div class="info-item"><label>Procedures</label><span>None</span></div>';
    return;
  }

  const customSingle = [];
  const singleProcs = [];
  const groupedProcs = {};

  arr.forEach(proc => {
    const pname = proc.procedure_name;
    const cproc = proc.custom_proc;
    const cgproc = proc.custom_group_proc;
    const gname = proc.group_name;
    const gid = proc.group_id;

    // Custom single (manual entry)
    if (cproc && !pname && (!gid || gid == 0)) {
      customSingle.push(cproc);
      return;
    }

    // Single predefined
    if (pname && (!gid || gid == 0)) {
      singleProcs.push(pname);
      return;
    }

    // Grouped procedures
    if (gid && (pname || cgproc)) {
      if (!groupedProcs[gid]) {
        groupedProcs[gid] = {
          group_name: gname || "Grouped Procedures",
          procedures: [],
          others: null
        };
      }
      if (pname) groupedProcs[gid].procedures.push(pname);
      if (cgproc) groupedProcs[gid].others = cgproc;
    }
  });

  // 1) Custom Singles
  customSingle.forEach(c => {
    const item = document.createElement("div");
    item.className = "info-item";
    item.innerHTML = `<label>Procedure</label><span>${escapeHtml(c)}</span>`;
    proceduresGrid.appendChild(item);
  });

  // 2) Single Procedures
  if (singleProcs.length) {
    const item = document.createElement("div");
    item.className = "info-item";
    item.innerHTML = `<label>Single Procedures</label><span>` +
      singleProcs.map(escapeHtml).join("<br>") + `</span>`;
    proceduresGrid.appendChild(item);
  }

  // 3) Grouped Procedures
  Object.values(groupedProcs).forEach(g => {
    const item = document.createElement("div");
    item.className = "info-item";
    item.innerHTML =
      `<label>${escapeHtml(g.group_name)}</label><span>` +
      (g.procedures.length ? g.procedures.map(escapeHtml).join("<br>") + "<br>" : "") +
      (g.others ? "<strong>Others:</strong> " + escapeHtml(g.others) : "") +
      `</span>`;
    proceduresGrid.appendChild(item);
  });

  // Fallback (shouldn't be needed unless all arrays empty)
  if (!customSingle.length && !singleProcs.length && Object.keys(groupedProcs).length === 0) {
    proceduresGrid.innerHTML =
      '<div class="info-item"><label>Procedures</label><span>None</span></div>';
  }
}

// HTML escape utility
function escapeHtml(str) {
  if (!str) return "";
  return str.replace(/[&<>'"]/g, c => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
  }[c]));
}

  }

  // =========== RECEPTIONIST DASHBOARD ===========

  // ------------ Receptionist Dashboard Search & Filter Only ------------

  // Bind all search/filter events for the receptionist patient table
  // ===========BIND RECEPTIONIST TABLE EVENTS VERSION 1===========
  // function bindRecepTableEvents() {
  //     const searchInput = document.getElementById('recepSearchPatient');
  //     const statusFilter = document.getElementById('recepAvailStatusFilter');
  //     const sortFilter = document.getElementById('recepPatientSortFilter');
  //     const tableBody = document.getElementById('recepPatientsTable');

  //     if (!searchInput || !statusFilter || !sortFilter || !tableBody) {
  //         console.error("Receptionist dashboard elements not found—check HTML IDs!");
  //         return;
  //     }

  //     // Main AJAX function for filtering/sorting/searching
  //     function applyRecepFiltersAjax() {
  //         const search = searchInput.value || '';
  //         const status = statusFilter.value || 'all';
  //         const sort = sortFilter.value || 'date_desc';
  //         tableBody.style.opacity = '0.5';

  //         $.ajax({
  //             url: "partials/home/search_avails.php",
  //             method: "POST",
  //             data: { search, availStatus: status, sort },
  //             success: function(resp) {
  //                 tableBody.innerHTML = resp;
  //                 tableBody.style.opacity = '1';
  //                 // Re-bind events for modal opening (details view) since table is replaced
  //                 initRecepModalHandlers();
  //             },
  //             error: function(err) {
  //                 tableBody.style.opacity = '1';
  //                 Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
  //             }
  //         });
  //     }

  //     // Attach events
  //     searchInput.addEventListener('keyup', applyRecepFiltersAjax);
  //     statusFilter.addEventListener('change', applyRecepFiltersAjax);
  //     sortFilter.addEventListener('change', applyRecepFiltersAjax);

  //     // Initial load (populate with defaults)
  //     applyRecepFiltersAjax();
  // }

  // ===========BIND RECEPTIONIST TABLE EVENTS VERSION 2===========
  function bindRecepTableEvents() {
    const statusDropdown = document.getElementById("recepStatusDropdown");
    const datePreset = document.getElementById("recepDatePreset");
    const dateFrom = document.getElementById("recepDateFrom");
    const dateTo = document.getElementById("recepDateTo");
    const dateToLabel = document.getElementById("recepDateToLabel");
    const searchInput = document.getElementById("recepSearchPatient");
    const sortFilter = document.getElementById("recepPatientSortFilter");
    const tableBody = document.getElementById("recepPatientsTable");

    function applyRecepFiltersAjax() {
      // Get status array:
      // const statusArr = Array.from(document.querySelectorAll('.recepStatusCheckbox:checked')).map(cb => cb.value);

      let statusArr = [];
      const statusVal = statusDropdown.value;

      if (statusVal === "all") {
        statusArr = ["Pending", "Completed", "Canceled"];
      } else {
        statusArr = [statusVal];
      }

      // Returns 'YYYY-MM-DD' in browser's local time
      function getLocalDateString(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, "0");
        const dd = String(dateObj.getDate()).padStart(2, "0");
        return `${yyyy}-${mm}-${dd}`;
      }

      // Get date range:
      let filterFrom = "";
      let filterTo = "";
      const now = new Date();
      const preset = datePreset.value;
      if (preset === "today") {
        filterFrom = filterTo = getLocalDateString(now);
      } else if (preset === "yesterday") {
        const d = new Date(now);
        d.setDate(d.getDate() - 1);
        filterFrom = filterTo = getLocalDateString(d);
      } else if (preset === "last7") {
        const d = new Date(now);
        d.setDate(d.getDate() - 6);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "last30") {
        const d = new Date(now);
        d.setDate(d.getDate() - 29);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "custom") {
        filterFrom = dateFrom.value;
        filterTo = dateTo.value;
      }

      const search = searchInput.value || "";
      const sort = sortFilter.value || "date_desc";
      tableBody.style.opacity = "0.5";

      $.ajax({
        url: "partials/home/search_avails.php",
        method: "POST",
        data: {
          statusArr,
          dateFrom: filterFrom,
          dateTo: filterTo,
          search,
          sort,
        },
        success: function (resp) {
          tableBody.innerHTML = resp;
          tableBody.style.opacity = "1";
          initRecepModalHandlers();
        },
        error: function (err) {
          tableBody.style.opacity = "1";
          Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
        },
      });
    }

    // UI logic for date picker presets
    // datePreset.addEventListener('change', function () {
    //   if (this.value === 'custom') {
    //     dateFrom.style.display = '';
    //     dateTo.style.display = '';
    //     dateToLabel.style.display = '';
    //   } else {
    //     dateFrom.style.display = 'none';
    //     dateTo.style.display = 'none';
    //     dateToLabel.style.display = 'none';
    //     // Optionally, reset values
    //     dateFrom.value = '';
    //     dateTo.value = '';
    //   }
    //   applyRecepFiltersAjax();
    // });

    datePreset.addEventListener("change", function () {
      const customDate = document.querySelector(".custom-date");
      if (this.value === "custom") {
        customDate.style.display = "flex";
      } else {
        customDate.style.display = "none";
        dateFrom.value = "";
        dateTo.value = "";
      }
      applyRecepFiltersAjax();
    });

    // Bind all filter change events:
    statusDropdown.addEventListener("change", applyRecepFiltersAjax);
    dateFrom.addEventListener("change", applyRecepFiltersAjax);
    dateTo.addEventListener("change", applyRecepFiltersAjax);
    searchInput.addEventListener("keyup", applyRecepFiltersAjax);
    sortFilter.addEventListener("change", applyRecepFiltersAjax);

    // Initial render:
    applyRecepFiltersAjax();
  }

  // Modal handler for viewing avail details
  function initRecepModalHandlers() {
    const modal = document.getElementById("viewAvailModal");
    const closeBtn = document.getElementById("recCloseAvailModalBtn");
    const proceduresGrid = document.getElementById("recModalProceduresGrid");
    const table = document.getElementById("recepPatientsTable");
    if (!modal || !closeBtn || !proceduresGrid || !table) return;

    table.onclick = function (e) {
      const btn = e.target.closest(".view-avail-btn");
      if (!btn) return;
      const avail_id = btn.getAttribute("data-avail_id");
      const fd = new FormData();
      fd.append("ajax_get_avail_info", "1");
      fd.append("avail_id", avail_id);
      fetch("partials/home/search_avails.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            fillRecepModal(
              data.service,
              data.procedures,
              data.patient,
              data.billing
            );
            modal.style.display = "block";
            closeBtn.focus();
          } else {
            Swal && Swal.fire("Error", data.message, "error");
          }
        });
    };
    closeBtn.onclick = closeRecepModal;
    modal.onclick = function (e) {
      if (e.target === modal) closeRecepModal();
    };
    window.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.style.display === "block")
        closeRecepModal();
    });

    function closeRecepModal() {
      modal.style.display = "none";
      document.getElementById("recModalServiceName").innerText = "";
      document.getElementById("recModalRequestedBy").innerText = "";
      document.getElementById("recModalDateAvailed").innerText = "";
      document.getElementById("recModalCaseNo").innerText = "";
      document.getElementById("recModalPackageName").innerText = ""; 
      document.getElementById("recModalBriefHistory").innerText = "";
      document.getElementById("recModalStatus").innerText = "";
      document.getElementById("recModalBillingStatus").innerText = "";
      proceduresGrid.innerHTML = "";
    }

    function fillRecepModal(service, procedures, patient, billing) {

      // Existing Service Details
      document.getElementById("recModalServiceName").innerText =
        service.service_name || "";
      document.getElementById("recModalRequestedBy").innerText =
        service.requested_by || "--";
      document.getElementById("recModalDateAvailed").innerText =
       formatDateAvailed (service.date_availed);
      document.getElementById("recModalCaseNo").innerText =
        service.case_no || "";
        document.getElementById("recModalPackageName").innerText =
    service.package_name || "--";
      document.getElementById("recModalBriefHistory").innerText =
        service.brief_history || "--";
      document.getElementById("recModalStatus").innerText =
        service.status || "";
        document.getElementById("recModalBillingStatus").innerText =
    service.billing_status || "--";
      fillRecepProceduresGrid(procedures);

      // Patient Details (optional, shown if patient exists)
      if (patient) {
        document.getElementById("recModalPatientName").innerText =
          patient.name || "";
        document.getElementById("recModalPatientDOB").innerText =
           formatDateDisplay(patient.dob);
        document.getElementById("recModalPatientSex").innerText =
          patient.sex || "";
        document.getElementById("recModalPatientPhone").innerText =
          patient.phone || "";
      }

      // Billing Details
      if (billing) {
        document.getElementById("recModalOR").innerText =
          billing.or_number || "";
        document.getElementById("recModalSubtotal").innerText =
          billing.amount_total || "";
        // Discount
        let discount = "";
        if (billing.discount_name) {
          discount =
            billing.discount_name +
            (billing.discount_value ? ` (${billing.discount_value}%)` : "");
        } else if (billing.custom_discount_value) {
          discount = `Custom (${billing.custom_discount_value}%)`;
        } else {
          discount = "None";
        }
        document.getElementById("recModalDiscount").innerText = discount;
        document.getElementById("recModalTotal").innerText =
          billing.discount_amount || billing.amount_total || "";
      }
    }

    function fillRecepProceduresGrid(arr) {
      proceduresGrid.innerHTML = "";
      if (!arr || arr.length === 0) {
        proceduresGrid.innerHTML =
          '<div class="info-item"><label>Procedures</label><span>None</span></div>';
        return;
      }

      const customSingle = [];
      const singleProcs = [];
      const groupedProcs = {};

      arr.forEach(proc => {
        const pname = proc.procedure_name;
        const cproc = proc.custom_proc;
        const cgproc = proc.custom_group_proc;
        const gname = proc.group_name;
        const gid = proc.group_id;

        // Custom single (manual entry)
        if (cproc && !pname && (!gid || gid == 0)) {
          customSingle.push(cproc);
          return;
        }

        // Single predefined
        if (pname && (!gid || gid == 0)) {
          singleProcs.push(pname);
          return;
        }

        // Grouped procedures
        if (gid && (pname || cgproc)) {
          if (!groupedProcs[gid]) {
            groupedProcs[gid] = {
              group_name: gname || "Grouped Procedures",
              procedures: [],
              others: null
            };
          }
          if (pname) groupedProcs[gid].procedures.push(pname);
          if (cgproc) groupedProcs[gid].others = cgproc;
        }
      });

      // 1) Custom Singles
      customSingle.forEach(c => {
        const item = document.createElement("div");
        item.className = "info-item";
        item.innerHTML = `<label>Procedure</label><span>${escapeHtml(c)}</span>`;
        proceduresGrid.appendChild(item);
      });

      // 2) Single Procedures
      if (singleProcs.length) {
        const item = document.createElement("div");
        item.className = "info-item";
        item.innerHTML = `<label>Single Procedures</label><span>` +
          singleProcs.map(escapeHtml).join("<br>") + `</span>`;
        proceduresGrid.appendChild(item);
      }

      // 3) Grouped Procedures
      Object.values(groupedProcs).forEach(g => {
        const item = document.createElement("div");
        item.className = "info-item";
        item.innerHTML =
          `<label>${escapeHtml(g.group_name)}</label><span>` +
          (g.procedures.length ? g.procedures.map(escapeHtml).join("<br>") + "<br>" : "") +
          (g.others ? "<strong>Others:</strong> " + escapeHtml(g.others) : "") +
          `</span>`;
        proceduresGrid.appendChild(item);
      });

      // Fallback (shouldn't be needed unless all arrays empty)
      if (!customSingle.length && !singleProcs.length && Object.keys(groupedProcs).length === 0) {
        proceduresGrid.innerHTML =
          '<div class="info-item"><label>Procedures</label><span>None</span></div>';
      }
    }

    // HTML escape utility
    function escapeHtml(str) {
      if (!str) return "";
      return str.replace(/[&<>'"]/g, c => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
      }[c]));
    }

    //   proceduresGrid.innerHTML = "";
    //   if (!arr || arr.length === 0) {
    //     proceduresGrid.innerHTML =
    //       '<div class="info-item"><label>Procedures</label><span>None</span></div>';
    //     return;
    //   }
    //   arr.forEach((proc) => {
    //     let label = proc.group_name || "Procedure";
    //     let value =
    //       proc.procedure_name ||
    //       proc.custom_proc ||
    //       proc.custom_group_proc ||
    //       "";
    //     const item = document.createElement("div");
    //     item.className = "info-item";
    //     item.innerHTML = `<label>${label}</label><span>${value}</span>`;
    //     proceduresGrid.appendChild(item);
    //   });
    // }
  }

  // Always rebind when the dashboard is loaded or after content is swapped
  // document.addEventListener('DOMContentLoaded', bindRecepTableEvents);

  // ==========View patient one-time handler==========
  let patientViewHandlerAttached = false;

  function addPatientViewHandlerOnce() {
    if (patientViewHandlerAttached) return;
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;
    mainContent.addEventListener(
      "click",
      function (e) {
        const btn = e.target.closest(".view-patient-btn");
        if (!btn) return;

        e.preventDefault();
        const patientId = btn.getAttribute("data-patient_id");

        fetch("partials/patients/view_patient.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "patient_id=" + encodeURIComponent(patientId),
        })
          .then((res) => res.text())
          .then((html) => {
            mainContent.innerHTML = html;
            if (typeof initPatientEditModal === "function")
              initPatientEditModal();
            // (rebind view/modal/archive etc if needed)
            // You could also call addPatientViewHandlerOnce again, if you re-render main-content
          })
          .catch((err) => {
            console.error(err);
            Swal.fire("Error", "Error loading patient details.", "error");
          });
      },
      false
    );
    patientViewHandlerAttached = true;
  }

  // ================VIEW PATIENT===============
  function initViewPatientButtons() {
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;

    // ========== Patients filters: status, sort, search ==========
    const statusFilter = document.getElementById("patientStatusFilter");
    const sortFilter = document.getElementById("patientSortFilter");
    const searchInput = document.getElementById("searchPatient");
    const tableBody = document.getElementById("patientsTable");

    function applyFiltersAjax() {
      const status = statusFilter?.value || "active";
      const sort = sortFilter?.value || "date_desc";
      const search = searchInput?.value || "";

      if (tableBody) showTableLoader("#patientsTable", 5);

      $.ajax({
        url: "partials/patients/search_patients.php",
        method: "POST",
        data: { search: search, status: status, sort: sort },
        success: function (resp) {
          $("#patientsTable").html(resp);
          if (tableBody) tableBody.style.opacity = "1";
          // Always rebind handlers after table is updated
          if (typeof initViewPatientButtons === "function")
            initViewPatientButtons();
        },
        error: function (err) {
          console.error(err);
          if (tableBody) tableBody.style.opacity = "1";
          Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
        },
      });
    }

    if (statusFilter) statusFilter.onchange = applyFiltersAjax;
    if (sortFilter) sortFilter.onchange = applyFiltersAjax;
    if (searchInput) searchInput.onkeyup = applyFiltersAjax;

    // ============ FILTER PATIENT FOR PERSONNEL VIEW ================
    /** For Personnel Filter (Completed/Canceled/All) **/
    const availStatusFilter = document.getElementById("availStatusFilter");
    const sortFilterPersonnel = document.getElementById("patientSortFilter");
    const searchInputPersonnel = document.getElementById("searchPatient");

    function applyPersonnelsFiltersAjax() {
      const availStatus = availStatusFilter?.value || "all";
      const sort = sortFilterPersonnel?.value || "date_desc";
      const search = searchInputPersonnel?.value || "";
      const tableBody = document.getElementById("patientsTable");

      showTableLoader("#patientsTable", 6);

      $.ajax({
        url: "partials/patients/search_patients.php",
        method: "POST",
        data: { search: search, availStatus: availStatus, sort: sort },
        success: function (resp) {
          $("#patientsTable").html(resp);
          if (tableBody) tableBody.style.opacity = "1";
          if (typeof initViewPatientButtons === "function")
            initViewPatientButtons();
        },
        error: function (err) {
          console.error(err);
          if (tableBody) tableBody.style.opacity = "1";
          Swal && Swal.fire("Error", "Failed to filter patient list.", "error");
        },
      });
    }
    if (availStatusFilter)
      availStatusFilter.onchange = applyPersonnelsFiltersAjax;
    if (sortFilterPersonnel)
      sortFilterPersonnel.onchange = applyPersonnelsFiltersAjax;
    if (searchInputPersonnel)
      searchInputPersonnel.onkeyup = applyPersonnelsFiltersAjax;

    //
  }

  // Initialize patient edit modal
  function initPatientEditModal() {
    const editBtn = document.getElementById("editPatientBtn"); // ID of the Edit button
    const modal = document.getElementById("editPatientModal"); // ID of the modal
    const form = document.getElementById("editPatientForm"); // ID of the edit form

    // IDs of input fields inside the form
    const firstNameInput = document.getElementById("patientFirstName");
    const middleNameInput = document.getElementById("patientMiddleName");
    const lastNameInput = document.getElementById("patientLastName");
    const sexInput = document.getElementById("patientSex");
    const dobInput = document.getElementById("patientDob");
    const phoneInput = document.getElementById("patientPhone");
    const addressInput = document.getElementById("patientAddress");
    const patientIdInput = document.getElementById("patientId"); // hidden input

    if (editBtn && modal) {
      editBtn.onclick = () => (modal.style.display = "block");
    }

    const closeBtn = document.getElementById("closeEditPatientModalBtn");
    if (closeBtn && modal) {
      closeBtn.onclick = function() {
        modal.style.display = "none";
      };
    }


    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();

        // Basic client-side validation
        const firstName = firstNameInput?.value.trim();
        const lastName = lastNameInput?.value.trim();
        const sex = sexInput?.value;
        const dob = dobInput?.value;
        const phone = phoneInput?.value.trim();
        const address = addressInput?.value.trim();

        if (!firstName || !lastName || !sex || !dob) {
          Swal.fire(
            "Warning",
            "Please fill out all required fields.",
            "warning"
          );
          return;
        }

        const today = new Date().toISOString().split("T")[0];
        if (dob > today) {
          Swal.fire(
            "Warning",
            // change this message
            "Date of Birth cannot be in the future.",
            "warning"
          );
          return;
        }

        if (phone && !/^\d{11}$/.test(phone)) {
          Swal.fire(
            "Warning",
            "Phone number must be exactly 11 digits.",
            "warning"
          );
          return;
        }

        if (address && address.length > 255) {
          Swal.fire("Warning", "Address is too long.", "warning");
          return;
        }

        const namePattern = /^[A-Za-z\s\-\.]+$/;
        if (!namePattern.test(firstName) || !namePattern.test(lastName)) {
          Swal.fire(
            "Warning",
            "First and Last names can only contain letters, spaces, hyphens, and periods.",
            "warning"
          );
          return;
        }

        // Prepare form data for POST
        const formData = new FormData(form);

        fetch("partials/patients/view_patient.php", {
          method: "POST",
          body: formData,
        })
          .then((res) => res.text())
          .then(() => {
            // Reload the patient partial after saving
            const patientId = patientIdInput?.value;
            if (!patientId) return;

            fetch("partials/patients/view_patient.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "patient_id=" + encodeURIComponent(patientId),
            })
              .then((res) => res.text())
              .then((html) => {
                document.getElementById("main-content").innerHTML = html;
                initViewPatientButtons(); // re-bind buttons for new content
                initPatientEditModal(); // re-bind edit modal
                Swal.fire({
                  icon: "success",
                  title: "Saved!",
                  text: "Patient information has been updated.",
                  timer: 1800,
                  showConfirmButton: false,
                });
              });
          })
          .catch((err) => {
            console.error(err);
            Swal.fire("Error", "Failed to save patient changes.", "error");
          });
      });
    }

    // Close modal if clicked outside
    window.onclick = function (e) {
      if (e.target === modal) closeModal();
    };

    document.querySelectorAll(".archivePatientForm").forEach((form) => {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const patientId = form.querySelector('input[name="patient_id"]').value;

        Swal.fire({
          title: "Are you sure?",
          text: "This patient will be archived!",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Yes, archive!",
        }).then((result) => {
          if (result.isConfirmed) {
            const formData = new FormData();
            formData.append("archive_patient", "1");
            formData.append("patient_id", patientId);

            fetch("partials/patients/view_patient.php", {
              method: "POST",
              body: formData,
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.status === "success") {
                  Swal.fire("Archived!", data.message, "success");

                  // Optionally reload patient list or update UI
                  if (typeof loadPage === "function") {
                    loadPage("partials/patients/patient.php");
                  }
                } else {
                  Swal.fire("Error", data.message, "error");
                }
              })
              .catch((err) => {
                console.error(err);
                Swal.fire("Error", "Failed to archive patient.", "error");
              });
          }
        });
      });
    });

    document.querySelectorAll(".restorePatientForm").forEach((form) => {
    form.addEventListener("submit", function (e) {
        e.preventDefault();
        const patientId = form.querySelector('input[name="patient_id"]').value;

        Swal.fire({
            title: "Are you sure?",
            text: "This patient will be restored!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, restore!",
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append("restore_patient", "1");
                formData.append("patient_id", patientId);

                fetch("partials/patients/view_patient.php", {
                    method: "POST",
                    body: formData,
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data.status === "success") {
                            Swal.fire("Restored!", data.message, "success");
                            if (typeof loadPage === "function") {
                                loadPage("partials/patients/patient.php");
                            }
                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch((err) => {
                        console.error(err);
                        Swal.fire("Error", "Failed to restore patient.", "error");
                    });
            }
        });
    });
});


  }

  // Helper function to check 18+ years
  function isAtLeast18YearsOld(dob) {
    const dobDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - dobDate.getFullYear();
    const m = today.getMonth() - dobDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) age--;
    return age >= 18;
  }

  window.initViewPatientButtons = initViewPatientButtons;

  // ====================VIEW PATIENT AVAILED SERVICES INSIDE VIEW PATIENT=====================

  let patientServiceHandlerAttached = false;

  function addPatientServiceHandlerOnce() {
    if (patientServiceHandlerAttached) return;
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;
    mainContent.addEventListener("click", function (e) {
      const link = e.target.closest(".view-service");
      if (!link) return;
      e.preventDefault();

      const availId = link.getAttribute("data-avail-id");
      if (!availId) return;

      fetch("partials/patients/view_patient.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `fetch_service_details=1&avail_id=${encodeURIComponent(availId)}`,
      })
        .then((res) => res.text())
        .then((html) => {
          const container = document.getElementById("serviceDetails");
          if (container) {
            container.innerHTML = html;
            showPatientServiceModal();
          }
        })
        .catch((err) => {
          console.error(err);
          alert("Error loading service details.");
        });
    });
    patientServiceHandlerAttached = true;
  }

  // REMEMBER COPY THIS CODE FOR THE SERVICE VIEW ALSO
  function initPatientAvailedServiceButtons() {
    const mainContent = document.getElementById("main-content");
    if (!mainContent) return;
  }

  function showPatientServiceModal() {
    const modal = document.getElementById("viewServiceModal");
    if (modal) modal.style.display = "block";
  }

  function closeViewModal() {
    const modal = document.getElementById("viewServiceModal");
    if (modal) modal.style.display = "none";
  }

  window.closeViewModal = closeViewModal;
  window.initPatientAvailedServiceButtons = initPatientAvailedServiceButtons;

  // =========ADD PATINENT PAGE============

  function initAddPatientButton() {
    const mainContent = document.getElementById("main-content");
    const addBtn = document.getElementById("addPatientBtn");
    if (!addBtn || !mainContent) return;

    addBtn.addEventListener("click", (e) => {
      e.preventDefault();

      fetch("partials/patients/add_patient.php")
        .then((res) => res.text())
        .then((html) => {
          mainContent.innerHTML = html;

          // After loading, initialize the add form
          if (typeof initAddPatient === "function") initAddPatient();
          if (typeof initPatientServiceToggles === "function")
            initPatientServiceToggles();
          if (typeof initbillingCalculator === "function")
            initbillingCalculator();
          if (typeof initPackageSelectHandler === "function")
            initPackageSelectHandler();
        })
        .catch((err) => {
          console.error(err);
          Swal.fire("Error", "Failed to load Add Patient form.", "error");
        });
    });
  }

  // // ==========ADD PATIENT SUBMIT V3 WITH SECURITY ADDED AND CONFIRMATION MODAL============
  // function initAddPatient() {
  //   const form = document.getElementById("addPatientForm");
  //   const cancelBtn = document.getElementById("cancelAddPatient");

  //   if (!form) return;

  //   // ---------- CANCEL BUTTON ----------
  //   if (cancelBtn) {
  //     cancelBtn.onclick = (e) => {
  //       e.preventDefault();
  //       if (typeof loadPage === "function") {
  //         loadPage("partials/patients/patient.php");
  //       }
  //     };
  //   }

  //   // submit listener with the new validation
  //   form.addEventListener("submit", function (e) {
  //     e.preventDefault();
  //     if (form.dataset.submitting === "true") return;

  //     // --------- CUSTOM PRICE VALIDATION ---------
  //     let bad = false;
  //     // Manual single procedure: custom price required if custom proc filled
  //     document
  //       .querySelectorAll('[name^="manual_procedure"]')
  //       .forEach(function (procInput) {
  //         const svcId = procInput.name.match(/\[(\d+)\]/)?.[1];
  //         const priceInput = document.querySelector(
  //           `[name="manual_custom_proc_price[${svcId}]"]`
  //         );
  //         if (procInput.value.trim() !== "") {
  //           if (
  //             !priceInput ||
  //             priceInput.value === "" ||
  //             Number(priceInput.value) <= 0
  //           ) {
  //             bad = true;
  //             if (priceInput) priceInput.classList.add("input-error");
  //             if (priceInput) priceInput.focus();
  //           } else {
  //             priceInput.classList.remove("input-error");
  //           }
  //         }
  //       });
  //     // Manual group "others": custom group price required
  //     document
  //       .querySelectorAll('input[name^="other_proc_group["]')
  //       .forEach(function (procInput) {
  //         const groupId = procInput.name.match(/\[(\d+)\]/)?.[1];
  //         const priceInput = document.querySelector(
  //           `[name="other_proc_group_price[${groupId}]"]`
  //         );
  //         if (procInput.value.trim() !== "") {
  //           if (
  //             !priceInput ||
  //             priceInput.value === "" ||
  //             Number(priceInput.value) <= 0
  //           ) {
  //             bad = true;
  //             if (priceInput) priceInput.classList.add("input-error");
  //             if (priceInput) priceInput.focus();
  //           } else {
  //             priceInput.classList.remove("input-error");
  //           }
  //         }
  //       });
  //     if (bad) {
  //       Swal.fire(
  //         "Error",
  //         "Please enter a valid custom price for all manual or group procedures.",
  //         "error"
  //       );
  //       return false;
  //     }
  //     // --------- END CUSTOM PRICE VALIDATION ---------

  //     // Continue with SweetAlert confirmation and AJAX submit as you had before
  //     Swal.fire({
  //       title: "Just to be sure...",
  //       text: "Are you sure you want to add this patient record? (You'll be able to edit it later)",
  //       icon: "question",
  //       showCancelButton: true,
  //       confirmButtonText: "Yes, save",
  //       cancelButtonText: "No, cancel",
  //     }).then((result) => {
  //       if (result.isConfirmed) {
  //         form.dataset.submitting = "true";

  //         // Enable all disabled inputs just before collecting FormData
  //         const prevDisabled = [];
  //         form.querySelectorAll(":disabled").forEach((el) => {
  //           prevDisabled.push(el);
  //           el.disabled = false;
  //         });

  //         const formData = new FormData(form);
  //         formData.append("add_patient", "1");

  //         // Restore disabled status if needed
  //         prevDisabled.forEach((el) => (el.disabled = true));

  //         fetch("partials/patients/add_patient.php", {
  //           method: "POST",
  //           body: formData,
  //           credentials: "same-origin",
  //           headers: { "X-Requested-With": "XMLHttpRequest" },
  //         })
  //           .then((response) => response.json())
  //           .then((data) => {

  //             if (data.includes("success")) {
  //               Swal.fire({
  //                 icon: "success",
  //                 title: "Patient Added!",
  //                 text: "The patient entry was saved successfully.",
  //               }).then(() => {
  //                 if (typeof loadPage === "function") {
  //                   loadPage("partials/patients/patient.php");
  //                 }
  //               });
  //             } else {
  //               Swal.fire("Error", "Server responded unexpectedly.", "error");
  //             }
  //           })
  //           .catch(() => {
  //             Swal.fire("Error", "Could not save patient.", "error");
  //           })
  //           .finally(() => {
  //             form.dataset.submitting = "false";
  //           });
  //       }
  //       // If canceled, do nothing, just stay on form.
  //     });
  //   });
  // }

  // window.initAddPatient = initAddPatient;

  // ==========ADD PATIENT SUBMIT V3 WITH SECURITY ADDED AND CONFIRMATION MODAL============
function initAddPatient() {
  const form = document.getElementById("addPatientForm");
  const cancelBtn = document.getElementById("cancelAddPatient");

  if (!form) return;

  bindServiceCheckboxBillingToggle(form);

  // ---------- CANCEL BUTTON ----------
  if (cancelBtn) {
    cancelBtn.onclick = (e) => {
      e.preventDefault();
      if (typeof loadPage === "function") {
        loadPage("partials/patients/patient.php");
      }
    };
  }

  // submit listener with the new validation
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (form.dataset.submitting === "true") return;

    // Check at least 1 service selected
    const checkedSvcs = form.querySelectorAll("input[name='selected_services[]']:checked");
    if (checkedSvcs.length < 1) {
      Swal.fire("Warning", "Please check at least 1 service.", "warning");
      return;
    }

    // For each checked service, require at least one procedure (checked or custom text)
    let allSvcsHaveProc = true;
    checkedSvcs.forEach(function(svc){
      const svcId = svc.value;
      const predefProcs = form.querySelectorAll(`input[name^='procedures[${svcId}]']:checked`);
      const customProc = form.querySelector(`input[name='manual_procedure[${svcId}]']`);
      const hasCustom = customProc && customProc.value.trim();
      if (predefProcs.length === 0 && !hasCustom) {
        allSvcsHaveProc = false;
      }
    });
    if (!allSvcsHaveProc) {
      Swal.fire("Warning", "Please input procedure for every selected service.", "warning");
      return;
    }


    // --------- CUSTOM PRICE VALIDATION ---------
    let bad = false;
    // Manual single procedure: custom price required if custom proc filled
    document
      .querySelectorAll('[name^="manual_procedure"]')
      .forEach(function (procInput) {
        const svcId = procInput.name.match(/\[(\d+)\]/)?.[1];
        const priceInput = document.querySelector(
          `[name="manual_custom_proc_price[${svcId}]"]`
        );
        if (procInput.value.trim() !== "") {
          if (
            !priceInput ||
            priceInput.value === "" ||
            Number(priceInput.value) <= 0
          ) {
            bad = true;
            if (priceInput) priceInput.classList.add("input-error");
            if (priceInput) priceInput.focus();
          } else {
            priceInput.classList.remove("input-error");
          }
        }
      });
    // Manual group "others": custom group price required
    document
      .querySelectorAll('input[name^="other_proc_group["]')
      .forEach(function (procInput) {
        const groupId = procInput.name.match(/\[(\d+)\]/)?.[1];
        const priceInput = document.querySelector(
          `[name="other_proc_group_price[${groupId}]"]`
        );
        if (procInput.value.trim() !== "") {
          if (
            !priceInput ||
            priceInput.value === "" ||
            Number(priceInput.value) <= 0
          ) {
            bad = true;
            if (priceInput) priceInput.classList.add("input-error");
            if (priceInput) priceInput.focus();
          } else {
            priceInput.classList.remove("input-error");
          }
        }
      });
    if (bad) {
      Swal.fire(
        "Error",
        "Please enter a valid custom price for all manual or group procedures.",
        "error"
      );
      return false;
    }
    // --------- END CUSTOM PRICE VALIDATION ---------

    // Continue with SweetAlert confirmation and AJAX submit
    Swal.fire({
      title: "Confirmation",
      text: "Are you sure you want to add this patient?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, save",
      cancelButtonText: "No, cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        form.dataset.submitting = "true";

        // Enable all disabled inputs just before collecting FormData
        const prevDisabled = [];
        form.querySelectorAll(":disabled").forEach((el) => {
          prevDisabled.push(el);
          el.disabled = false;
        });

        const formData = new FormData(form);
        formData.append("add_patient", "1");

        // Restore disabled status if needed
        prevDisabled.forEach((el) => (el.disabled = true));

        fetch("partials/patients/add_patient.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
          headers: { "X-Requested-With": "XMLHttpRequest" },
        })
          .then((response) => {
            // Check if response is ok
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
          })
          .then((data) => {
            // console.log("Server response:", data); // ✅ Debug log
            
            // ✅ FIXED: Check the status property of the JSON object
            if (data.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Patient Added!",
                text: data.message || "The patient entry was saved successfully.",
              }).then(() => {
                if (typeof loadPage === "function") {
                  loadPage("partials/patients/patient.php");
                }
              });
            } else {
              // ✅ Show the actual error message from PHP
              Swal.fire("Error", data.message || "Failed to add patient.", "error");
            }
          })
          .catch((err) => {
            console.error("Add patient error:", err); // ✅ Debug log
            Swal.fire("Error", "Could not save patient: " + err.message, "error");
          })
          .finally(() => {
            form.dataset.submitting = "false";
          });
      }
      // If canceled, do nothing, just stay on form.
    });
  });
}

window.initAddPatient = initAddPatient;

  function initPatientServiceToggles() {
    // Service selection checkboxes at the top
    document
      .querySelectorAll('#serviceSelectionForm input[type="checkbox"]')
      .forEach((checkbox) => {
        checkbox.addEventListener("change", function () {
          const serviceId = this.value;
          const serviceGroup = document.querySelector(
            `.service-group[data-service-id="${serviceId}"]`
          );
          const serviceFields = document.getElementById(`fields-${serviceId}`);
          const procedureSection = document.getElementById(
            `section-${serviceId}`
          );

          if (this.checked) {
            // Show service fields
            if (serviceGroup) serviceGroup.classList.add("active");
            if (serviceFields) {
              serviceFields
                .querySelectorAll("input, select, textarea")
                .forEach((el) => (el.disabled = false));
            }

            // Show procedure section
            if (procedureSection) {
              procedureSection.classList.add("show");
            

              procedureSection
                .querySelectorAll('input[type="checkbox"]')
                .forEach((el) => {
                  // If a package is active, lock only package-included procedures
                  if (
                    window.__patientPackageActive &&
                    window.__lockedProcedureIds &&
                    window.__lockedProcedureIds.includes(
                      String(el.value.split(":")[0])
                    )
                  ) {
                    el.disabled = true;
                  } else {
                    el.disabled = false;
                  }
                });
            }

            // PATCH 1: Enable/disable custom price input with manual procedure
            const manualProcInput = serviceFields
              ? serviceFields.querySelector('input[name^="manual_procedure"]')
              : null;
            const manualProcPriceInput = serviceFields
              ? serviceFields.querySelector(
                  'input[name^="manual_custom_proc_price"]'
                )
              : null;

            if (manualProcInput && manualProcPriceInput) {
              // Initial state
              manualProcPriceInput.disabled =
                manualProcInput.value.trim() === "";
              // Toggle enable/disable based on procedure text
              manualProcInput.addEventListener("input", function () {
                const hasText = this.value.trim() !== "";
                manualProcPriceInput.disabled = !hasText;
                if (!hasText) {
                  manualProcPriceInput.value = "";
                }
                // Recompute when the field is cleared
                if (
                  typeof window.__computePatientbillingTotals === "function"
                ) {
                  window.__computePatientbillingTotals();
                }
              });

              // Recompute totals whenever manual custom price changes
              manualProcPriceInput.addEventListener("input", function () {
                if (
                  typeof window.__computePatientbillingTotals === "function"
                ) {
                  window.__computePatientbillingTotals();
                }
              });
            }

            // PATCH 2: Enable/disable custom group price input with "Others"
            if (procedureSection) {
              const otherInputs = procedureSection.querySelectorAll(
                'input[name^="other_proc_group["]'
              );
              const otherPriceInputs = procedureSection.querySelectorAll(
                'input[name^="other_proc_group_price["]'
              );

              otherInputs.forEach((otherInput, i) => {
                const priceInput = otherPriceInputs[i];
                if (!priceInput) return;

                // Enable the "Others" text field, but keep price disabled initially
                otherInput.disabled = false;
                priceInput.disabled = otherInput.value.trim() === "";

                // Add input listener to enable/disable price based on "Others" text
                otherInput.addEventListener("input", function () {
                  if (this.value.trim() !== "") {
                    priceInput.disabled = false;
                  } else {
                    priceInput.value = "";
                    priceInput.disabled = true;
                  }
                });
              });
            }
          } else {
            // Hide service fields
            if (serviceGroup) serviceGroup.classList.remove("active");
            if (serviceFields) {
              serviceFields
                .querySelectorAll("input, select, textarea")
                .forEach((el) => {
                  el.disabled = true;
                  el.value = "";
                });
            }

            // Hide procedure section
            if (procedureSection) {
              procedureSection.classList.remove("show");
              procedureSection.querySelectorAll("input").forEach((el) => {
                if (el.type === "checkbox") el.checked = false;
                el.disabled = true;
                if (el.type !== "checkbox") el.value = "";
              });
            }
          }

          // Update billing visibility
          updateBillingVisibility();
        });
      });

    // **Hide all service sections on page load**
    document.querySelectorAll(".service-group").forEach((group) => {
      group.classList.remove("active");
      const serviceFields = group.querySelector('[id^="fields-"]');
      if (serviceFields) {
        serviceFields
          .querySelectorAll("input, select, textarea")
          .forEach((el) => {
            el.disabled = true;
          });
      }
    });

    // **Hide all procedure sections on page load**
    document.querySelectorAll(".procedure-section").forEach((section) => {
      section.classList.remove("show");
      section.querySelectorAll("input").forEach((el) => {
        el.disabled = true;
      });
    });

    // Initial state check for pre-checked checkboxes
    document
      .querySelectorAll('#serviceSelectionForm input[type="checkbox"]')
      .forEach((checkbox) => {
        if (checkbox.checked) {
          checkbox.dispatchEvent(new Event("change"));
        }
      });
  }

  // ==============BILLING VISIBILITY UPDATE==============
  function updateBillingVisibility() {
    const billingSection = document.getElementById("billingSection");
    const hasActiveService = document.querySelector(
      '#serviceSelectionForm input[type="checkbox"]:checked'
    );
    const hasActiveProcedure = document.querySelector(
      '.procedure-section.show input[type="checkbox"]:checked'
    );

    if (billingSection) {
      if (hasActiveService || hasActiveProcedure) {
        billingSection.style.display = "block";
      } else {
        billingSection.style.display = "none";
      }
    }
  }

  // ==============INITIALIZE ON PAGE LOAD==============
  document.addEventListener("DOMContentLoaded", function () {
    initPatientServiceToggles();
    updateBillingVisibility();
  });

  // window.initPatientServiceToggles = initPatientServiceToggles;

  // =================billing CALCULATOR ONLY========================
  // ========== billing calculator & wiring for Add Patient ==========
  function initbillingCalculator() {
    // Immediately return if the page doesn't have the form container yet
    // (we'll call this after injection so it will find elements)
    (function () {
      // Helpers
      function parsePriceAttr(el) {
        if (!el) return 0;
        const v = el.getAttribute("data-price");
        if (!v) return 0;
        const n = parseFloat(String(v).replace(/,/g, "")) || 0;
        return n;
      }
      function toFixed2(n) {
        return (Number(n) || 0).toFixed(2);
      }

      // Elements (may be null if not on page)
      const billingSection = document.getElementById("billingSection");
      const totalAmountEl = document.getElementById("totalAmount");
      const finalAmountEl = document.getElementById("finalAmount");
      const discountSelect = document.getElementById("discountSelect");
      const customDiscount = document.getElementById("customDiscount");
      const discountValueInput = document.getElementById(
        "discount_value_input"
      );

      // Guard: no billing UI present
      if (
        !totalAmountEl ||
        !finalAmountEl ||
        !discountSelect ||
        !customDiscount ||
        !billingSection
      ) {
        return;
      }

      // Find current service checkboxes and procedure checkboxes
      function getServiceCheckboxes() {
        return Array.from(
          document.querySelectorAll('input[name="selected_services[]"]')
        );
      }
      function getProcedureCheckboxes() {
        return Array.from(
          document.querySelectorAll(
            'input[name^="procedures"][type="checkbox"]'
          )
        );
      }
      function getProceduresForService(serviceId) {
        // Restrict to checkboxes within the section for that service
        const section = document.getElementById("section-" + serviceId);
        if (!section) return [];
        return Array.from(
          section.querySelectorAll('input[name^="procedures"][type="checkbox"]')
        );
      }

      // Show/hide service sections (keeps parity with your toggles)
      function updateServiceSectionDisplay() {
        getServiceCheckboxes().forEach((cb) => {
          const sid = cb.value;
          const section = document.getElementById("section-" + sid);
          if (!section) return;
          if (cb.checked) section.classList.add("show");
          else section.classList.remove("show");
        });
      }

      // Show/hide the billing section based on any selected service
      function updatebillingSectionVisibility() {
        const anyChecked = getServiceCheckboxes().some((cb) => cb.checked);
        billingSection.style.display = anyChecked ? "block" : "none";
      }

      // When a service is unchecked we must clear its procedure checkboxes (per your rule)
      function resetProceduresForUncheckedServices() {
        getServiceCheckboxes().forEach((cb) => {
          const sid = cb.value;
          if (!cb.checked) {
            const procs = getProceduresForService(sid);
            procs.forEach((i) => {
              if (i.checked) {
                i.checked = false;
                // trigger a change event for other listeners if necessary
                i.dispatchEvent(new Event("change", { bubbles: true }));
              }
            });
          }
        });
      }

      // Compute total & final amount using data-price attributes

      // computeTotals v2
      function computeTotals() {
        const packageSelect = document.getElementById("packageSelect");
        const packageActive =
          window.__patientPackageActive && packageSelect && packageSelect.value;
        const packagePrice = Number(window.__currentPackagePrice) || 0;

        const procCheckboxes = getProcedureCheckboxes();
        let subtotal = 0;
        let extrasSubtotal = 0;

        // Used to prevent string/number id mismatch
        const lockedProcIds = (window.__lockedProcedureIds || []).map(String);

        procCheckboxes.forEach((chk) => {
          if (chk.checked) {
            const procId = chk.value.split(":")[0];
            const price = parsePriceAttr(chk);

            if (packageActive && lockedProcIds.includes(String(procId))) {
              // This procedure is part of the selected package. Price not counted toward extras.
            } else {
              extrasSubtotal += price;
            }
            subtotal += price;
          }
        });

        // Add custom price for each non-empty manual procedure (per checked service)
        document
          .querySelectorAll('input[name^="manual_procedure"]')
          .forEach((procInput) => {
            const svcId = procInput.name.match(/\[(\d+)\]/)?.[1];
            const priceInput = document.querySelector(
              `[name="manual_custom_proc_price[${svcId}]"]`
            );
            const svcCheckbox = document.getElementById("service-" + svcId);
            if (
              svcCheckbox &&
              svcCheckbox.checked &&
              procInput.value.trim() !== "" &&
              priceInput &&
              priceInput.value !== ""
            ) {
              const price = parseFloat(priceInput.value);
              if (!isNaN(price) && price > 0) {
                extrasSubtotal += price;
                subtotal += price;
              }
            }
          });

        // Add custom group price for each "Others" group with text
        document
          .querySelectorAll('input[name^="other_proc_group["]')
          .forEach((procInput) => {
            const groupId = procInput.name.match(/\[(\d+)\]/)?.[1];
            const priceInput = document.querySelector(
              `[name="other_proc_group_price[${groupId}]"]`
            );
            if (
              procInput.value.trim() !== "" &&
              priceInput &&
              priceInput.value !== ""
            ) {
              const price = parseFloat(priceInput.value);
              if (!isNaN(price) && price > 0) {
                // Cannot reliably lock these by group, so treat all as extra for discount unless you have stronger package checks
                extrasSubtotal += price;
                subtotal += price;
              }
            }
          });

        // Determine discount percentage: custom overrides select
        let percent = 0;
        const customVal = (customDiscount.value || "").toString().trim();
        if (customVal !== "") {
          let v = parseFloat(customVal);
          if (isNaN(v)) v = 0;
          if (v < 0) v = 0;
          if (v > 100) v = 100;
          percent = v;
        } else {
          const sel = discountSelect.options[discountSelect.selectedIndex];
          if (sel && sel.dataset && sel.dataset.value) {
            const v = parseFloat(sel.dataset.value);
            if (!isNaN(v)) percent = v;
          }
        }

        if (packageActive) {
          // Package is active: Only extrasSubtotal gets the discount
          const discountValue = extrasSubtotal * (percent / 100.0);
          const finalVal = packagePrice + (extrasSubtotal - discountValue);

          totalAmountEl.value = toFixed2(packagePrice + extrasSubtotal);
          finalAmountEl.value = toFixed2(finalVal);
        } else {
          // No package active: everything is discountable
          const discountValue = subtotal * (percent / 100.0);
          const finalVal = subtotal - discountValue;
          totalAmountEl.value = toFixed2(subtotal);
          finalAmountEl.value = toFixed2(finalVal);
        }
      }

      // Discount select / custom interaction
      discountSelect.addEventListener("change", function () {
        // Get percent from selected option's data-value
        var sel = this.options[this.selectedIndex];
        var value = sel ? sel.getAttribute("data-value") : "0";
        discountValueInput.value = value || "0";
        if (this.value) {
          customDiscount.value = "";
          customDiscount.disabled = true;
        } else {
          customDiscount.disabled = false;
        }
        computeTotals();
      });

      customDiscount.addEventListener("input", function () {
        if (this.value !== "") {
          discountSelect.value = "";
          discountSelect.disabled = true;
          discountValueInput.value = "0";
        } else {
          discountSelect.disabled = false;
          // If custom discount emptied, restore chosen discountSelect's value
          var sel = discountSelect.options[discountSelect.selectedIndex];
          var value = sel ? sel.getAttribute("data-value") : "0";
          discountValueInput.value = value || "0";
        }
        computeTotals();
      });

      // Debounced update to avoid thrashing
      let computeTimer = null;
      function scheduleCompute() {
        if (computeTimer) clearTimeout(computeTimer);
        computeTimer = setTimeout(() => {
          computeTotals();
          computeTimer = null;
        }, 60);
      }

      // Global change delegation to handle dynamically created inputs
      document.addEventListener("change", function (e) {
        const t = e.target;
        if (!t) return;

        // Service checkbox toggled
        if (t.matches('input[name="selected_services[]"]')) {
          // When service toggles: reset procedures if unchecked, show/hide section and billing
          resetProceduresForUncheckedServices();
          updateServiceSectionDisplay();
          updatebillingSectionVisibility();
          scheduleCompute();
          return;
        }

        // Procedure checkbox toggled
        if (t.matches('input[name^="procedures"][type="checkbox"]')) {
          // ensure billing is visible only if some service selected
          updatebillingSectionVisibility();
          scheduleCompute();
          return;
        }

        // If discount or custom changed they'll already trigger compute via their own handlers
      });

      // Run initial state updates (useful if form is injected after DOMContentLoaded)
      function initOnce() {
        updateServiceSectionDisplay();
        updatebillingSectionVisibility();
        computeTotals();
      }

      // Listen for changes to any manual custom price field
      document
        .querySelectorAll('input[name^="manual_custom_proc_price"]')
        .forEach((input) => {
          input.addEventListener("input", function () {
            computeTotals();
          });
        });

      // Listen for changes to any group "Others" custom price field
      document
        .querySelectorAll('input[name^="other_proc_group_price"]')
        .forEach((input) => {
          input.addEventListener("input", function () {
            computeTotals();
          });
        });

      // If the form was injected after DOMContentLoaded (most common), call initOnce shortly
      setTimeout(initOnce, 50);

      // Expose a manual compute call if needed
      window.__computePatientbillingTotals = computeTotals;
    })();
  }

  // Expose it globally so you can call it when form is injected
  window.initbillingCalculator = initbillingCalculator;

  // ==========PACKAGE SELECT HANDLER IN AVAIL SERVICE FORM==========

  // =====version 2 with improvements=========
  function initPackageSelectHandler() {
    const packageSelect = document.getElementById("packageSelect");
    const totalAmountEl = document.getElementById("totalAmount");
    const finalAmountEl = document.getElementById("finalAmount");
    const billingSection = document.getElementById("billingSection");

    // Store locked service/procedure ids globally for billing and reset
    window.__lockedServiceIds = [];
    window.__lockedProcedureIds = [];

    if (!packageSelect) return;

    // ========VERSION 2=========
    packageSelect.addEventListener("change", function () {
      const pkgId = this.value;

      // Uncheck and enable all services and their procedures
      document
        .querySelectorAll('input[name="selected_services[]"]')
        .forEach((cb) => {
          cb.checked = false;
          cb.disabled = false;
          cb.dispatchEvent(new Event("change"));
        });
      document.querySelectorAll('input[name^="procedures"]').forEach((cb) => {
        cb.checked = false;
        cb.disabled = false;
      });

      // Reset locked ids
      window.__lockedServiceIds = [];
      window.__lockedProcedureIds = [];

      if (!pkgId) {
        window.__patientPackageActive = false;
        window.__currentPackagePrice = undefined;
        document.getElementById("discountSelect")?.removeAttribute("disabled");
        document.getElementById("customDiscount")?.removeAttribute("disabled");
        if (totalAmountEl) totalAmountEl.value = "0.00";
        if (finalAmountEl) finalAmountEl.value = "0.00";
        if (billingSection) billingSection.style.display = "none";
        window.__computePatientbillingTotals &&
          window.__computePatientbillingTotals();
        return;
      }

      // Fetch package details and auto-lock services/procedures
      fetch("partials/patients/avail_service.php", {
        method: "POST",
        body: new URLSearchParams({ ajax_get_package: 1, package_id: pkgId }),
        credentials: "same-origin",
      })
        .then((res) => res.json())
        .then((pkg) => {
          // Uncheck and enable all again to be sure
          document
            .querySelectorAll('input[name="selected_services[]"]')
            .forEach((cb) => {
              cb.checked = false;
              cb.disabled = false;
            });
          document
            .querySelectorAll('input[name^="procedures"]')
            .forEach((cb) => {
              cb.checked = false;
              cb.disabled = false;
            });

          // Lock the right services and their procedures

          // ========version 4 logic=========
          for (const procedureId of pkg.procedures) {
            document
              .querySelectorAll('input[type="checkbox"][name^="procedures"]')
              .forEach((procCB) => {
                if (
                  String(procCB.value).startsWith(String(procedureId) + ":")
                ) {
                  procCB.checked = true;
                  procCB.disabled = true;
                  procCB.setAttribute("disabled", "disabled");
                  window.__lockedProcedureIds.push(String(procedureId));
                  const svcMatch = procCB.name.match(/^procedures\[(\d+)\]/);
                  if (svcMatch) {
                    const svcId = svcMatch[1];
                    const svcCB = document.getElementById("service-" + svcId);
                    if (svcCB) {
                      svcCB.checked = true;
                      svcCB.disabled = true;
                      svcCB.setAttribute("disabled", "disabled");
                      if (!window.__lockedServiceIds.includes(svcId + ""))
                        window.__lockedServiceIds.push(svcId + "");
                    }
                  }
                }
              });
          }

          // Trigger .change on checked services to show fill-up/procedure forms
          setTimeout(() => {
            document
              .querySelectorAll('input[name="selected_services[]"]')
              .forEach((cb) => {
                if (cb.checked)
                  cb.dispatchEvent(new Event("change", { bubbles: true }));
              });
          }, 10);

          // Show billing and set price to package's discounted price (lock editing)
          if (billingSection) billingSection.style.display = "block";
          if (totalAmountEl)
            totalAmountEl.value = Number(pkg.discount_price || 0).toFixed(2);
          if (finalAmountEl)
            finalAmountEl.value = Number(pkg.discount_price || 0).toFixed(2);

          // document.getElementById('discountSelect')?.setAttribute('disabled', 'disabled');
          // document.getElementById('customDiscount')?.setAttribute('disabled', 'disabled');

          window.__patientPackageActive = true;
          window.__currentPackagePrice = pkg.discount_price;
          window.__computePatientbillingTotals &&
            window.__computePatientbillingTotals();
        });
    });

    document.addEventListener("change", function (e) {
      const t = e.target;
      if (
        (t.matches('input[name="selected_services[]"]') && !t.disabled) ||
        (t.matches('input[name^="procedures"]') && !t.disabled)
      ) {
        // Only reset package if ALL procedures (including locked/package ones) are unchecked
        const anyProcedureChecked = Array.from(
          document.querySelectorAll('input[name^="procedures"]')
        ).some((cb) => cb.checked);
        // If NO procedures are checked, then reset the package
        if (!anyProcedureChecked && window.__patientPackageActive) {
          packageSelect.value = "";
          packageSelect.dispatchEvent(new Event("change"));
        }
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initPackageSelectHandler();
  });

  // ===================show hide billing section==================
function toggleBillingSection(show) {
    const orInput = document.getElementById("orNumber");
    const billingSection = document.getElementById("billingSection");
    if (orInput && billingSection) {
        if (show) {
            billingSection.style.display = "";
            orInput.required = true;
        } else {
            billingSection.style.display = "none";
            orInput.required = false;
        }
    }
}

// Call on checkbox change for service selection
function bindServiceCheckboxBillingToggle(form) {
    form.querySelectorAll("input[name='selected_services[]']").forEach(function(cb){
        cb.addEventListener('change', function(){
            const checked = form.querySelectorAll("input[name='selected_services[]']:checked").length;
            toggleBillingSection(checked > 0);
        });
    });
    // Also on startup (in case of pre-checked/edit case)
    const checkedInitial = form.querySelectorAll("input[name='selected_services[]']:checked").length;
    toggleBillingSection(checkedInitial > 0);
}

// Main initializer (call inside or after initAvailServiceForm)
function initPatientBillingAutoToggle() {
    const form = document.getElementById("availServiceForm");
    if (form) {
        bindServiceCheckboxBillingToggle(form);
    }
}

// In your page setup/after AJAX loads availServiceForm:
initPatientBillingAutoToggle();


  // ===================AVAIL SERVICE FOR EXISTING PATIENT PAGE========================
  // 1. Avail Service Button Loader
  function initAvailServiceButton() {
    $(document)
      .off("click", "#availServiceBtn")
      .on("click", "#availServiceBtn", function () {
        var patientId = $("#patientId").val() || $(this).data("patient-id");
        if (!patientId) {
          Swal.fire("Error", "No patient selected.");
          return;
        }

        $.ajax({
          url: "partials/patients/avail_service.php",
          method: "POST",
          data: { patient_id: patientId },
          success: function (html) {
            $("#main-content").html(html);
            initPatientServiceToggles();
            initbillingCalculator();
            initCancelAvailService();
            initAvailServiceForm();
            initPackageSelectHandler();
            initPatientBillingAutoToggle();
          },
          error: function () {
            Swal.fire("Error", "Failed to load avail service form.", "error");
          },
        });
      });
  }

  // 2. Cancel Avail Button Loader
  function initCancelAvailService() {
    $(document)
      .off("click", "#cancelAvailService")
      .on("click", "#cancelAvailService", function (e) {
        e.preventDefault();
        var patientId = $("input[name='patient_id']").val();
        if (!patientId) {
          Swal.fire("Error", "No patient selected.");
          return;
        }
        $.ajax({
          url: "partials/patients/view_patient.php",
          method: "POST",
          data: { patient_id: patientId },
          success: function (html) {
            $("#main-content").html(html);
            // Call your patient init functions here if needed
            initAvailServiceButton();
            initViewPatientButtons();
            initPatientEditModal();
            initPatientServiceToggles();
            initbillingCalculator();
            initCancelAvailService();
            initAvailServiceForm();
            initViewPatientButtons();
            initAvailServiceButton();
            initPatientEditModal();
          },
        });
      });
  }

  // ===================EXISTING PATIENT ADD NEW SERVICE SUBMIT======================
  function initAvailServiceForm() {
    const form = document.getElementById("availServiceForm");
    // You do NOT need the modal or manual confirm button anymore!

    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (form.dataset.submitting === "true") return;

      // Check at least 1 service selected
      const checkedSvcs = form.querySelectorAll("input[name='selected_services[]']:checked");
      if (checkedSvcs.length < 1) {
        Swal.fire("Warning", "Please check at least 1 service.", "warning");
        return;
      }

      // For each checked service, require at least one procedure (checked or custom text)
      let allSvcsHaveProc = true;
      checkedSvcs.forEach(function(svc){
        const svcId = svc.value;
        const predefProcs = form.querySelectorAll(`input[name^='procedures[${svcId}]']:checked`);
        const customProc = form.querySelector(`input[name='manual_procedure[${svcId}]']`);
        const hasCustom = customProc && customProc.value.trim();
        if (predefProcs.length === 0 && !hasCustom) {
          allSvcsHaveProc = false;
        }
      });
      if (!allSvcsHaveProc) {
        Swal.fire("Warning", "Please input procedure for every selected service.", "warning");
        return;
      }


      // Show basic confirmation Swal before AJAX submit
      Swal.fire({
        title: "Confirmation",
        text: "Are you sure you want to save this service avail?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, save",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (result.isConfirmed) {
          form.dataset.submitting = "true";

          // Enable all disabled inputs just before collecting FormData
          const prevDisabled = [];
          form.querySelectorAll(":disabled").forEach((el) => {
            prevDisabled.push(el);
            el.disabled = false;
          });

          const formData = new FormData(form);

          // Restore disabled status if needed
          prevDisabled.forEach((el) => (el.disabled = true));

          fetch("partials/patients/avail_service.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
            headers: { "X-Requested-With": "XMLHttpRequest" },
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.status === "success") {
                Swal.fire({
                  icon: "success",
                  title: "Service Availed!",
                  text: data.message,
                }).then(() => {
                  // Reload patient view
                  const patientId = form.patient_id.value;
                  $.ajax({
                    url: "partials/patients/view_patient.php",
                    method: "POST",
                    data: { patient_id: patientId },
                    success: function (html) {
                      $("#main-content").html(html);
                      initPatientServiceToggles();
                      initbillingCalculator();
                      initCancelAvailService();
                      initAvailServiceForm();
                      initViewPatientButtons();
                      initAvailServiceButton();
                      initPatientEditModal();
                    },
                  });
                });
              } else {
                Swal.fire("Error", data.message, "error");
              }
            })
            .catch(() => {
              Swal.fire("Error", "Failed to save service avail.", "error");
            })
            .finally(() => {
              form.dataset.submitting = "false";
            });
        }
        // If cancel, do nothing
      });
    });
  }

  // =================PERSONNEL DASHBAORD========================
  function refreshPersonnelDashboardCounts() {
    fetch("partials/home/home_personnel.php", {
      method: "POST",
      body: new URLSearchParams({ ajax_get_dashboard_counts: "1" }),
    })
      .then((res) => res.json())
      .then((data) => {
        // Update each dashboard box value by order
        if (data) {
          // You can use more specific selectors if your .dashboard-box-modern layout changes
          document.querySelector(
            ".dashboard-boxes .dashboard-box-modern:nth-child(1) .dashboard-box-value"
          ).innerText = data.patients_served ?? 0;
          document.querySelector(
            ".dashboard-boxes .dashboard-box-modern:nth-child(2) .dashboard-box-value"
          ).innerText = data.total_requests ?? 0;
          document.querySelector(
            ".dashboard-boxes .dashboard-box-modern:nth-child(3) .dashboard-box-value"
          ).innerText = data.completed_today ?? 0;
          document.querySelector(
            ".dashboard-boxes .dashboard-box-modern:nth-child(4) .dashboard-box-value"
          ).innerText = data.pending_today ?? 0;
        }
      });
  }

  // =============BIND PERSONNEL VERSION 2=========================
  function bindPersonnelTableEvents() {
    const statusDropdown = document.getElementById("personnelStatusDropdown");
    const datePreset = document.getElementById("personnelDatePreset");
    const dateFrom = document.getElementById("personnelDateFrom");
    const dateTo = document.getElementById("personnelDateTo");
    const dateToLabel = document.getElementById("personnelDateToLabel");
    const searchInput = document.getElementById("personnelSearchPatient");
    const sortFilter = document.getElementById("personnelSortFilter");
    const tableBody = document.getElementById("pendingRequestsTable");

    function applyPersonnelFiltersAjax() {
      // let statusArr = Array.from(document.querySelectorAll('.personnelStatusCheckbox:checked')).map(cb => cb.value);
      // if (statusArr.length === 0) statusArr = ['Pending','Completed','Canceled'];

      let statusArr = [];
      const statusVal = statusDropdown.value;

      if (statusVal === "all") {
        statusArr = ["Pending", "Completed", "Canceled"];
      } else {
        statusArr = [statusVal];
      }

      // Returns 'YYYY-MM-DD' in browser's local time
      function getLocalDateString(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, "0");
        const dd = String(dateObj.getDate()).padStart(2, "0");
        return `${yyyy}-${mm}-${dd}`;
      }

      // Get date range:
      let filterFrom = "";
      let filterTo = "";
      const now = new Date();
      const preset = datePreset.value;
      if (preset === "today") {
        filterFrom = filterTo = getLocalDateString(now);
      } else if (preset === "yesterday") {
        const d = new Date(now);
        d.setDate(d.getDate() - 1);
        filterFrom = filterTo = getLocalDateString(d);
      } else if (preset === "last7") {
        const d = new Date(now);
        d.setDate(d.getDate() - 6);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "last30") {
        const d = new Date(now);
        d.setDate(d.getDate() - 29);
        filterFrom = getLocalDateString(d);
        filterTo = getLocalDateString(now);
      } else if (preset === "custom") {
        filterFrom = dateFrom.value;
        filterTo = dateTo.value;
      }

      const search = searchInput.value || "";
      const sort = sortFilter.value || "date_desc";
      tableBody.style.opacity = "0.5";

      $.ajax({
        url: "partials/home/search_avails.php",
        method: "POST",
        data: {
          statusArr,
          dateFrom: filterFrom,
          dateTo: filterTo,
          search,
          sort,
          personnel: 1,
        },
        success: function (resp) {
          tableBody.innerHTML = resp;
          tableBody.style.opacity = "1";
          initRequestModals();
        },
        error: function () {
          tableBody.style.opacity = "1";
          Swal.fire("Error", "Failed to filter requests.", "error");
        },
      });
    }

      window.applyPersonnelFiltersAjax = applyPersonnelFiltersAjax;

    datePreset.addEventListener("change", function () {
      const customDate = document.querySelector(".custom-date");
      if (this.value === "custom") {
        customDate.style.display = "flex";
      } else {
        customDate.style.display = "none";
        dateFrom.value = "";
        dateTo.value = "";
      }
      applyPersonnelFiltersAjax();
    });

    statusDropdown.addEventListener("change", applyPersonnelFiltersAjax);
    dateFrom.addEventListener("change", applyPersonnelFiltersAjax);
    dateTo.addEventListener("change", applyPersonnelFiltersAjax);
    searchInput.addEventListener("keyup", applyPersonnelFiltersAjax);
    sortFilter.addEventListener("change", applyPersonnelFiltersAjax);

    applyPersonnelFiltersAjax();
  }



  // =================PENDING REQUESTS MODAL FOR PERSONNEL========================
  function initRequestModals() {
    // Elements
    const viewModal = document.getElementById("viewRequestModal");
    const completeBtn = document.getElementById("completeRequestBtn");
    const cancelBtn = document.getElementById("cancelRequestBtn");
    const closeBtn = document.getElementById("closeRequestModalBtn");
    const proceduresGrid = document.getElementById("modalProceduresGrid");
    const pendingTable = document.getElementById("pendingRequestsTable");
    const pendingBtn = document.getElementById("pendingRequestBtn");

    if (
      !viewModal ||
      !completeBtn ||
      !cancelBtn ||
      !closeBtn ||
      !proceduresGrid ||
      !pendingTable
    )
      return;

    // Delegated event for the table (always up-to-date, works after AJAX replace)
    pendingTable.onclick = function (e) {
      const btn = e.target.closest(".view-avail-btn");
      if (!btn) return;
      const avail_id = btn.dataset.avail_id;
      const service_id = btn.dataset.service_id; // <-- ADDED: grab service_id from row

      // Fetch avail info
      const fd = new FormData();
      fd.append("ajax_get_avail_info", "1");
      fd.append("avail_id", avail_id);
      fetch("partials/home/home_personnel.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            fillServiceInfo(data.service, data.patient, data.billing);
            fillProceduresGrid(data.procedures);
            viewModal.style.display = "block";
            // Set both ids on buttons
            completeBtn.dataset.avail_id = avail_id;
            completeBtn.dataset.service_id = service_id; // <-- ADDED
            cancelBtn.dataset.avail_id = avail_id;
            cancelBtn.dataset.service_id = service_id; // <-- ADDED
            completeBtn.focus();
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    };

    // Complete
    completeBtn.onclick = function () {
      const avail_id = this.dataset.avail_id;
      const service_id = this.dataset.service_id;
      const fd = new FormData();
      fd.append("ajax_set_avail_status", "1");
      fd.append("avail_id", avail_id);
      fd.append("service_id", service_id);
      fd.append("status", "Completed");
      fetch("partials/home/home_personnel.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            Swal.fire("Success", "Marked as completed!", "success");
            // Update status cell in table (not remove row)
            const tr = document
              .querySelector(
                `.view-avail-btn[data-avail_id="${avail_id}"][data-service_id="${service_id}"]`
              )
              ?.closest("tr");
            if (tr) {
              tr.querySelector("td:nth-child(6)").innerText = "Completed"; // status cell
            }
            closeModalAndReset();
            refreshPersonnelDashboardCounts();
            applyPersonnelFiltersAjax();
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    };

    // Cancel
    cancelBtn.onclick = function () {
      const avail_id = this.dataset.avail_id;
      const service_id = this.dataset.service_id;
      const fd = new FormData();
      fd.append("ajax_set_avail_status", "1");
      fd.append("avail_id", avail_id);
      fd.append("service_id", service_id);
      fd.append("status", "Canceled");
      fetch("partials/home/home_personnel.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            Swal.fire("Canceled", "Marked as canceled.", "info");
            const tr = document
              .querySelector(
                `.view-avail-btn[data-avail_id="${avail_id}"][data-service_id="${service_id}"]`
              )
              ?.closest("tr");
            if (tr) {
              tr.querySelector("td:nth-child(6)").innerText = "Canceled"; // status cell
            }
            closeModalAndReset();
            refreshPersonnelDashboardCounts();
            applyPersonnelFiltersAjax();
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    };

    // Pending
    pendingBtn.onclick = function () {
      const avail_id = completeBtn.dataset.avail_id; // Use same ids as in Complete/Cancel
      const service_id = completeBtn.dataset.service_id;
      const fd = new FormData();
      fd.append("ajax_set_avail_status", "1");
      fd.append("avail_id", avail_id);
      fd.append("service_id", service_id);
      fd.append("status", "Pending");
      fetch("partials/home/home_personnel.php", { method: "POST", body: fd })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            Swal.fire("Reset", "Marked as pending.", "info");
            const tr = document
              .querySelector(
                `.view-avail-btn[data-avail_id="${avail_id}"][data-service_id="${service_id}"]`
              )
              ?.closest("tr");
            if (tr) {
              tr.querySelector("td:nth-child(6)").innerText = "Pending"; // status cell
            }
            closeModalAndReset();
            refreshPersonnelDashboardCounts();
            applyPersonnelFiltersAjax();
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    };


    // Close/Reset
    closeBtn.onclick = closeModalAndReset;
    viewModal.onclick = function (e) {
      if (e.target === viewModal) closeModalAndReset();
    };
    window.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && viewModal.style.display === "block")
        closeModalAndReset();
    });

    function closeModalAndReset() {
      viewModal.style.display = "none";
      document.getElementById("modalServiceName").innerText = "";
      document.getElementById("modalRequestedBy").innerText = "";
      document.getElementById("modalDateAvailed").innerText = "";
      document.getElementById("modalCaseNo").innerText = "";
      document.getElementById("modalPackageName").innerText = ""; 
      document.getElementById("modalBriefHistory").innerText = "";
      document.getElementById("modalStatus").innerText = "";
      document.getElementById("modalBillingStatus").innerText = ""; 

      proceduresGrid.innerHTML = "";
    }

    function fillServiceInfo(service, patient, billing) {
        // SERVICE DETAILS
        document.getElementById("modalServiceName").innerText =
          service.service_name || "";
        document.getElementById("modalRequestedBy").innerText =
          service.requested_by || "--";
        document.getElementById("modalDateAvailed").innerText =
          formatDateAvailed(service.date_availed);
        document.getElementById("modalCaseNo").innerText =
          service.case_no || "";
          document.getElementById("modalPackageName").innerText =
          service.package_name || "";
        document.getElementById("modalBriefHistory").innerText =
          service.brief_history || "--";
        document.getElementById("modalStatus").innerText =
          service.status || "";
          document.getElementById("modalBillingStatus").innerText =
    service.billing_status || "--";

        // PATIENT (for personnel modal IDs)
        const pn = document.getElementById("modalPatientName");
        const pdob = document.getElementById("modalPatientDOB");
        const psex = document.getElementById("modalPatientSex");
        const pphone = document.getElementById("modalPatientPhone");
        if (pn && patient) pn.innerText = patient.name || "";
        if (pdob && patient) pdob.innerText = formatDateDisplay(patient.dob);
        if (psex && patient) psex.innerText = patient.sex || "";
        if (pphone && patient) pphone.innerText = patient.phone || "";
        if (pn && !patient) pn.innerText = "";
        if (pdob && !patient) pdob.innerText = "";
        if (psex && !patient) psex.innerText = "";
        if (pphone && !patient) pphone.innerText = "";

        // BILLING
        if (billing) {
          document.getElementById("modalOR").innerText =
            billing.or_number || "";
          document.getElementById("modalSubtotal").innerText =
            billing.amount_total || "";
          let discount = "";
          if (billing.discount_name) {
            discount =
              billing.discount_name +
              (billing.discount_value ? ` (${billing.discount_value}%)` : "");
          } else if (billing.custom_discount_value) {
            discount = `Custom (${billing.custom_discount_value}%)`;
          } else {
            discount = "None";
          }
          document.getElementById("modalDiscount").innerText = discount;
          document.getElementById("modalTotal").innerText =
            billing.discount_amount || billing.amount_total || "";
        } else {
          document.getElementById("modalOR").innerText = "";
          document.getElementById("modalSubtotal").innerText = "";
          document.getElementById("modalDiscount").innerText = "";
          document.getElementById("modalTotal").innerText = "";
        }

       // ✅ FIX: Use local date, not UTC
      const dateStr = service.date_availed.substr(0, 10); // 'YYYY-MM-DD' from database
      const now = new Date();
      
      // Helper to get local date string
      function getLocalDateString(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, "0");
        const dd = String(dateObj.getDate()).padStart(2, "0");
        return `${yyyy}-${mm}-${dd}`;
      }
      
      const todayStr = getLocalDateString(now); // ✅ Now uses local timezone
      const showPending = (dateStr === todayStr);

      // Button logic
      if (service.status === "Pending") {
        completeBtn.style.display = "";
        cancelBtn.style.display = "";
        pendingBtn.style.display = "none";
      } else {
        completeBtn.style.display = "none";
        cancelBtn.style.display = "none";
        pendingBtn.style.display = showPending ? "" : "none";
      }

       
      }

      function fillProceduresGrid(arr) {
          const proceduresGrid = document.getElementById("modalProceduresGrid");
          proceduresGrid.innerHTML = "";
          if (!arr || arr.length === 0) {
            proceduresGrid.innerHTML =
              '<div class="info-item"><label>Procedures</label><span>None</span></div>';
            return;
          }
          const customSingle = [];
          const singleProcs = [];
          const groupedProcs = {};
          arr.forEach(proc => {
            const pname = proc.procedure_name;
            const cproc = proc.custom_proc;
            const cgproc = proc.custom_group_proc;
            const gname = proc.group_name;
            const gid = proc.group_id;
            // Custom single (manual entry)
            if (cproc && !pname && (!gid || gid == 0)) {
              customSingle.push(cproc);
              return;
            }
            // Single predefined
            if (pname && (!gid || gid == 0)) {
              singleProcs.push(pname);
              return;
            }
            // Grouped procedures
            if (gid && (pname || cgproc)) {
              if (!groupedProcs[gid]) {
                groupedProcs[gid] = {
                  group_name: gname || "Grouped Procedures",
                  procedures: [],
                  others: null
                };
              }
              if (pname) groupedProcs[gid].procedures.push(pname);
              if (cgproc) groupedProcs[gid].others = cgproc;
            }
          });
          // 1) Custom Singles
          customSingle.forEach(c => {
            const item = document.createElement("div");
            item.className = "info-item";
            item.innerHTML = `<label>Procedure</label><span>${escapeHtml(c)}</span>`;
            proceduresGrid.appendChild(item);
          });
          // 2) Single Procedures
          if (singleProcs.length) {
            const item = document.createElement("div");
            item.className = "info-item";
            item.innerHTML = `<label>Single Procedures</label><span>` +
              singleProcs.map(escapeHtml).join("<br>") + `</span>`;
            proceduresGrid.appendChild(item);
          }
          // 3) Grouped Procedures
          Object.values(groupedProcs).forEach(g => {
            const item = document.createElement("div");
            item.className = "info-item";
            item.innerHTML =
              `<label>${escapeHtml(g.group_name)}</label><span>` +
              (g.procedures.length ? g.procedures.map(escapeHtml).join("<br>") + "<br>" : "") +
              (g.others ? "<strong>Others:</strong> " + escapeHtml(g.others) : "") +
              `</span>`;
            proceduresGrid.appendChild(item);
          });
          // Fallback
          if (!customSingle.length && !singleProcs.length && Object.keys(groupedProcs).length === 0) {
            proceduresGrid.innerHTML =
              '<div class="info-item"><label>Procedures</label><span>None</span></div>';
          }
        }
        function escapeHtml(str) {
          if (!str) return "";
          return str.replace(/[&<>'"]/g, c => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
          }[c]));
        }

  }

  // ====================MANAGE DISCOUNTS========================
  function initDiscountModals() {
    // small escape helper (prefer window.escapeHtml if present)
    const escapeHtml =
      window.escapeHtml ||
      function (s) {
        return String(s).replace(/[&<>"']/g, function (m) {
          return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          }[m];
        });
      };

    const addBtn = document.getElementById("addDiscountBtn");
    const addModal = document.getElementById("addDiscountModal");
    const addForm = document.getElementById("addDiscountForm");

    const editModal = document.getElementById("editDiscountModal");
    const editForm = document.getElementById("editDiscountForm");

    // Expose close functions globally (your pattern)
    function closeAddDiscountModal() {
      if (addModal) addModal.style.display = "none";
      if (addForm) addForm.reset();
    }
    window.closeAddDiscountModal = closeAddDiscountModal;

    function closeEditDiscountModal() {
      if (editModal) editModal.style.display = "none";
      if (editForm) editForm.reset();
    }
    window.closeEditDiscountModal = closeEditDiscountModal;

    // Open add modal
    if (addBtn && addModal) {
      addBtn.onclick = () => {
        if (addForm) addForm.reset();
        addModal.style.display = "block";
      };
    }

    // Submit add discount via AJAX
    if (addForm) {
      addForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const name = document.getElementById("add_discount_name")?.value.trim();
        const value = document
          .getElementById("add_discount_value")
          ?.value.trim();

        if (!name || value === "") {
          Swal.fire("Validation", "Please fill in all fields.", "warning");
          return;
        }

        const formData = new FormData(addForm);
        formData.append("ajax_add_discount", "1");

        const submitBtn = addForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch("partials/discounts/discounts.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then((res) => {
            const ct = res.headers.get("content-type") || "";
            if (ct.indexOf("application/json") !== -1) return res.json();
            return res.text().then((t) => {
              throw new Error("Server returned HTML: " + t);
            });
          })
          .then((data) => {
            if (submitBtn) submitBtn.disabled = false;
            if (data.status === "success") {
              closeAddDiscountModal();
              Swal.fire({
                icon: "success",
                title: "Added",
                text: data.message,
                timer: 1400,
                showConfirmButton: false,
              });
              // prepend row to table
              const tableBody = document.getElementById("discountsTable");
              if (tableBody && data.discount_id) {
                const emptyRow = tableBody.querySelector("tr td[colspan]");
                if (emptyRow) emptyRow.parentNode.removeChild(emptyRow);

                const tr = document.createElement("tr");
                tr.dataset.id = data.discount_id;
                const val =
                  typeof data.discount_value !== "undefined"
                    ? String(data.discount_value).replace(/\.00$/, "")
                    : "";
                tr.innerHTML = `
                <td>${escapeHtml(data.discount_name)}</td>
                <td>${escapeHtml(val)}%</td>
                <td>${escapeHtml(data.date_created || "")}</td>   <!-- ✅ ADD DATE HERE -->
                <td>
                  <button type="button" class="btn btn-outline btn-sm view-discount-btn" 
                    data-id="${data.discount_id}" 
                    data-name="${escapeHtml(data.discount_name)}" 
                    data-value="${escapeHtml(data.discount_value)}">
                    <i class="fas fa-eye"></i> View
                  </button>
                  <button type="button" class="btn btn-outline btn-sm delete-discount-btn" 
                    data-id="${data.discount_id}">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </td>
              `;
                tableBody.prepend(tr);
                // rebind newly added buttons
                bindViewButtons();
                bindDeleteButtons();
              }
            } else {
              Swal.fire(
                "Error",
                data.message || "Failed to add discount",
                "error"
              );
            }
          })
          .catch((err) => {
            if (submitBtn) submitBtn.disabled = false;
            console.error("Add discount AJAX error:", err);
            Swal.fire(
              "Error",
              "AJAX request failed (check console/network).",
              "error"
            );
          });
      });
    }

    // Bind view/edit buttons
    function bindViewButtons() {
      document.querySelectorAll(".view-discount-btn").forEach((btn) => {
        // remove previous to avoid double-binding in dynamic reloads
        btn.onclick = function () {
          const id = this.dataset.id;
          const name = this.dataset.name;
          const value = this.dataset.value;

          document.getElementById("edit_discount_id").value = id;
          document.getElementById("edit_discount_name").value = name;
          document.getElementById("edit_discount_value").value = value;

          if (editModal) editModal.style.display = "block";
        };
      });
    }
    bindViewButtons();

    // Edit discount submit
    if (editForm) {
      editForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const id = document.getElementById("edit_discount_id")?.value;
        const name = document
          .getElementById("edit_discount_name")
          ?.value.trim();
        const value = document
          .getElementById("edit_discount_value")
          ?.value.trim();

        if (!id || !name || value === "") {
          Swal.fire("Validation", "Please fill in all fields.", "warning");
          return;
        }

        const formData = new FormData(editForm);
        formData.append("ajax_edit_discount", "1");

        const submitBtn = editForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch("partials/discounts/discounts.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then((res) => {
            const ct = res.headers.get("content-type") || "";
            if (ct.indexOf("application/json") !== -1) return res.json();
            return res.text().then((t) => {
              throw new Error("Server returned HTML: " + t);
            });
          })
          .then((data) => {
            if (submitBtn) submitBtn.disabled = false;
            if (data.status === "success") {
              closeEditDiscountModal();
              Swal.fire({
                icon: "success",
                title: "Updated",
                text: data.message,
                timer: 1200,
                showConfirmButton: false,
              });
              // update table row if exists
              const tr = document.querySelector(
                'tr[data-id="' + data.discount_id + '"]'
              );
              if (tr) {
                const tds = tr.querySelectorAll("td");
                if (tds.length >= 2) {
                  tds[0].textContent = data.discount_name;
                  tds[1].textContent =
                    String(data.discount_value).replace(/\.00$/, "") + "%";
                }
                // update data attributes on view button
                const viewBtn = tr.querySelector(".view-discount-btn");
                if (viewBtn) {
                  viewBtn.dataset.name = data.discount_name;
                  viewBtn.dataset.value = data.discount_value;
                }
              } else {
                // if row not found, reload page as fallback
                setTimeout(() => {
                  location.reload();
                }, 800);
              }
            } else {
              Swal.fire(
                "Error",
                data.message || "Failed to update discount",
                "error"
              );
            }
          })
          .catch((err) => {
            if (submitBtn) submitBtn.disabled = false;
            console.error("Edit discount AJAX error:", err);
            Swal.fire(
              "Error",
              "AJAX request failed (check console/network).",
              "error"
            );
          });
      });
    }

    // Bind delete buttons
    // Bind delete (archive) buttons
    function bindDeleteButtons() {
      document.querySelectorAll(".delete-discount-btn").forEach((btn) => {
        btn.onclick = function () {
          const id = this.dataset.id;
          Swal.fire({
            title: "Archive discount?",
            text: "This will archive the account",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, archive",
            cancelButtonText: "Cancel",
          }).then((result) => {
            if (result.isConfirmed) {
              const fd = new FormData();
              fd.append("ajax_delete_discount", "1"); // still using this flag
              fd.append("discount_id", id);

              fetch("partials/discounts/discounts.php", {
                method: "POST",
                body: fd,
                credentials: "same-origin",
              })
                .then((res) => {
                  const ct = res.headers.get("content-type") || "";
                  if (ct.indexOf("application/json") !== -1) return res.json();
                  return res.text().then((t) => {
                    throw new Error("Server returned HTML: " + t);
                  });
                })
                .then((data) => {
                  if (data.status === "success") {
                    Swal.fire("Archived", data.message, "success");
                    // remove row from table
                    const tr = document.querySelector(
                      'tr[data-id="' + id + '"]'
                    );
                    if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
                  } else {
                    Swal.fire(
                      "Error",
                      data.message || "Unable to archive",
                      "error"
                    );
                  }
                })
                .catch((err) => {
                  console.error("Archive discount AJAX error:", err);
                  Swal.fire(
                    "Error",
                    "AJAX request failed (check console/network).",
                    "error"
                  );
                });
            }
          });
        };
      });
    }

    bindDeleteButtons();

    // Close modals when clicking outside (same pattern you used elsewhere)
    window.addEventListener("click", function (e) {
      if (e.target === addModal) closeAddDiscountModal();
      if (e.target === editModal) closeEditDiscountModal();
    });
  }

  // ====================MANAGE PACKAGES========================
  function setupAutoCalc() {
    const checkboxes = document.querySelectorAll(
      '#add_proc_group input[type="checkbox"]'
    );
    const regPrice = document.getElementById("add_reg_price");
    const discountVal = document.getElementById("add_discount_value");
    const discountPrice = document.getElementById("add_discount_price");
    function recalc() {
      let total = 0;
      checkboxes.forEach((cb) => {
        if (cb.checked) total += parseFloat(cb.dataset.price || 0);
      });
      regPrice.value = total.toFixed(2);
      const disc = parseFloat(discountVal.value) || 0;
      discountPrice.value = (total - (total * disc) / 100).toFixed(2);
    }
    checkboxes.forEach((cb) => (cb.onchange = recalc));
    discountVal.oninput = recalc;
  }

  function setupEditAutoCalc() {
    const checkboxes = document.querySelectorAll(
      '#edit_proc_group input[type="checkbox"]'
    );
    const regPrice = document.getElementById("edit_reg_price");
    const discountVal = document.getElementById("edit_discount_value");
    const discountPrice = document.getElementById("edit_discount_price");
    function recalc() {
      let total = 0;
      checkboxes.forEach((cb) => {
        if (cb.checked) total += parseFloat(cb.dataset.price || 0);
      });
      regPrice.value = total.toFixed(2);
      const disc = parseFloat(discountVal.value) || 0;
      discountPrice.value = (total - (total * disc) / 100).toFixed(2);
    }
    checkboxes.forEach((cb) => (cb.onchange = recalc));
    discountVal.oninput = recalc;
  }

  function initPackagesModals() {
    const addBtn = document.getElementById("addPackageBtn");
    if (!addBtn) return;

    addBtn.onclick = function () {
      fetch("partials/packages/packages.php", {
        method: "POST",
        body: new URLSearchParams({ ajax_get_procedures: 1 }),
        credentials: "same-origin",
      })
        .then((res) => res.json())
        .then((data) => {
          const procDiv = document.getElementById("add_proc_group");
          procDiv.innerHTML = "";
          for (const [cat, items] of Object.entries(data)) {
            const catDiv = document.createElement("div");
            catDiv.className = "procedure-category";
            catDiv.innerHTML = `<strong>${cat}</strong><div></div>`;
            items.forEach((proc) => {
              const label = document.createElement("label");
              label.innerHTML = `<input type="checkbox" name="procedures[]" value="${proc.procedure_id}" data-price="${proc.procedure_price || 0}">
                    ${proc.procedure_name} <span class="price-badge">₱${parseFloat(proc.procedure_price || 0).toFixed(2)}</span>`;
              catDiv.querySelector("div").appendChild(label);
            });
            procDiv.appendChild(catDiv);
          }
          document.getElementById("addPackageModal").style.display = "block";
          setupAutoCalc();
          const addForm = document.getElementById("addPackageForm");
          addForm.onsubmit = function (e) {
            e.preventDefault();
            const formData = new FormData(addForm);
            formData.append("ajax_add_package", "1");
            fetch("partials/packages/packages.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.status === "success") {
                  Swal.fire("Success", "Package added!", "success").then(() => {
                    document.getElementById("addPackageModal").style.display =
                      "none";
                    loadPage("partials/packages/packages.php");
                  });
                } else {
                  Swal.fire("Error", data.message, "error");
                }
              });
          };
        });
    };

    document.querySelectorAll(".view-package-btn").forEach((btn) => {
      btn.onclick = function () {
        fetch("partials/packages/packages.php", {
          method: "POST",
          body: new URLSearchParams({
            ajax_get_package: 1,
            package_id: btn.dataset.id,
          }),
          credentials: "same-origin",
        })
          .then((res) => res.json())
          .then((pkg) => {
            document.getElementById("edit_package_id").value = btn.dataset.id;
            document.getElementById("edit_package_name").value =
              pkg.package_name;
            document.getElementById("edit_reg_price").value = pkg.reg_price;
            document.getElementById("edit_discount_value").value =
              pkg.discount_value;
            document.getElementById("edit_discount_price").value =
              pkg.discount_price;

            const procDiv = document.getElementById("edit_proc_group");
            procDiv.innerHTML = "";
            for (const [cat, items] of Object.entries(pkg.all_procedures)) {
              const catDiv = document.createElement("div");
              catDiv.className = "procedure-category";
              catDiv.innerHTML = `<strong>${cat}</strong><div></div>`;
              items.forEach((proc) => {
                const checked =
                  pkg.procedures.indexOf(proc.procedure_id) !== -1
                    ? "checked"
                    : "";
                const label = document.createElement("label");
                label.innerHTML = `<input type="checkbox" name="procedures[]" value="${proc.procedure_id}" data-price="${proc.procedure_price || 0}" ${checked}>
                        ${proc.procedure_name} <span class="price-badge">₱${parseFloat(proc.procedure_price || 0).toFixed(2)}</span>`;
                catDiv.querySelector("div").appendChild(label);
              });
              procDiv.appendChild(catDiv);
            }
            document.getElementById("editPackageModal").style.display = "block";
            setupEditAutoCalc();
            const editForm = document.getElementById("editPackageForm");
            editForm.onsubmit = function (e) {
              e.preventDefault();
              const formData = new FormData(editForm);
              formData.append("ajax_edit_package", "1");
              fetch("partials/packages/packages.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin",
              })
                .then((res) => res.json())
                .then((data) => {
                  document.getElementById("editPackageModal").style.display =
                    "none";
                  if (data.status === "success") {
                    Swal.fire("Success", "Package updated!", "success").then(
                      () => {
                        loadPage("partials/packages/packages.php");
                      }
                    );
                  } else {
                    Swal.fire("Error", data.message, "error");
                  }
                });
            };
          });
      };
    });

    document.querySelectorAll(".delete-package-btn").forEach((btn) => {
      btn.onclick = function () {
        const id = btn.dataset.id;
        Swal.fire({
          title: "Are you sure?",
          text: "This Package will be removed.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, I'm sure",
          cancelButtonText: "Cancel",
        }).then((result) => {
          if (result.isConfirmed) {
            const fd = new FormData();
            fd.append("ajax_delete_package", "1");
            fd.append("package_id", id);
            fetch("partials/packages/packages.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.status === "success") {
                  Swal.fire("Archived", data.message, "success");
                  const tr = btn.closest("tr");
                  if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
                } else {
                  Swal.fire("Error", data.message, "error");
                }
              })
              .catch((err) => {
                Swal.fire("Error", "AJAX request failed.", "error");
              });
          }
        });
      };
    });
  }

  function initReportControls() {
    const reportType = document.getElementById("reportType");
    const yearFilter = document.getElementById("yearFilter");
    const monthFilter = document.getElementById("monthFilter");
    const dayFilter = document.getElementById("dayFilter");
    const yearAnnual = document.getElementById("report_year");
    const yearDaily = document.getElementById("report_year_daily");

    if (!reportType || !yearFilter || !monthFilter || !dayFilter) {
      console.warn("Report controls not present!");
      return;
    }
    function updateControls() {
      if (reportType.value === "Monthly & Annual Sale") {
        yearFilter.style.display = "";
        monthFilter.style.display = "none";
        dayFilter.style.display = "none";
        if (yearAnnual) yearAnnual.disabled = false;
        if (yearDaily) yearDaily.disabled = true;
      } else if (reportType.value === "Daily Sale") {
        yearFilter.style.display = "none";
        monthFilter.style.display = "";
        dayFilter.style.display = "none";
        if (yearDaily) yearDaily.disabled = false;
        if (yearAnnual) yearAnnual.disabled = true;
      } else if (reportType.value === "Detailed Daily") {
        yearFilter.style.display = "none";
        monthFilter.style.display = "none";
        dayFilter.style.display = "";
        if (yearDaily) yearDaily.disabled = true;
        if (yearAnnual) yearAnnual.disabled = true;
      } else {
        yearFilter.style.display = "none";
        monthFilter.style.display = "none";
        dayFilter.style.display = "none";
        if (yearDaily) yearDaily.disabled = true;
        if (yearAnnual) yearAnnual.disabled = true;
      }
    }
    updateControls();
    reportType.addEventListener("change", updateControls);
  }

  function initReportFiltersAJAX() {
    const filterForm = document.getElementById("reportFilterForm");
    const reportTableContainer = document.getElementById(
      "reportTableContainer"
    );
    if (!filterForm || !reportTableContainer) return;

    filterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      // Clean form: only enable the active year select for submission
      const reportType = document.getElementById("reportType");
      const yearAnnual = document.getElementById("report_year");
      const yearDaily = document.getElementById("report_year_daily");
      if (reportType.value === "Monthly & Annual Sale") {
        if (yearAnnual) yearAnnual.disabled = false;
        if (yearDaily) yearDaily.disabled = true;
      } else {
        if (yearAnnual) yearAnnual.disabled = true;
        if (yearDaily) yearDaily.disabled = false;
      }

      const formData = new FormData(filterForm);
      reportTableContainer.innerHTML =
        // '<div style="text-align:center;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#28a745;"></i> Generating report...</div>'
        `<div style="display:flex;justify-content:center;align-items:center;min-height:300px;">
            <i class="fas fa-spinner fa-spin" style="font-size:60px;color:#007bff"></i>
          </div>`;

      fetch("partials/reports/reports_generate.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.text())
        .then((html) => {
          reportTableContainer.innerHTML = html;
        })
        .catch(() => {
          reportTableContainer.innerHTML =
            '<div class="text-danger">Failed to generate report.</div>';
        });
    });
  }

  // // After AJAX page load, call:
  // initReportControls();
  // initReportFiltersAJAX();

  function initReportExportExcel() {
    const exportExcelBtn = document.getElementById("exportExcelBtn");
    if (exportExcelBtn) {
      exportExcelBtn.addEventListener("click", function () {
        const reportType = document.getElementById("reportType").value;
        let params = `report_type=${encodeURIComponent(reportType)}`;

        if (reportType === "Monthly & Annual Sale") {
          params += `&report_year=${encodeURIComponent(document.getElementById("report_year").value)}`;
        } else if (reportType === "Daily Sale") {
          params += `&report_month=${encodeURIComponent(document.getElementById("report_month").value)}`;
          params += `&report_year_daily=${encodeURIComponent(document.getElementById("report_year_daily").value)}`;
        } else if (reportType === "Detailed Daily") {
          params += `&report_date=${encodeURIComponent(document.getElementById("report_date").value)}`;
        }

        window.open(`partials/reports/export_excel.php?${params}`);
      });
    }
  }


  // LOGS 

 function bindLogsFilterEvents() {
  const logSearch = document.getElementById('logSearch');
  const logStatus = document.getElementById('logStatusDropdown');
  const logSort = document.getElementById('logSortFilter');
  const logDatePreset = document.getElementById('logDatePreset');
  const logDateFrom = document.getElementById('logDateFrom');
  const logDateTo = document.getElementById('logDateTo');

  // Show/hide custom range on preset change
  if (logDatePreset) {
    logDatePreset.onchange = function () {
      if (this.value === "custom") {
        if (logDateFrom) logDateFrom.style.display = "";
        if (logDateTo) logDateTo.style.display = "";
      } else {
        if (logDateFrom) logDateFrom.style.display = "none";
        if (logDateTo) logDateTo.style.display = "none";
        if (logDateFrom) logDateFrom.value = "";
        if (logDateTo) logDateTo.value = "";
      }
      applyPersonnelLogFilter();
    };
  }
  if (logSearch) logSearch.onkeyup = applyPersonnelLogFilter;
  if (logStatus) logStatus.onchange = applyPersonnelLogFilter;
  if (logSort) logSort.onchange = applyPersonnelLogFilter;
  if (logDateFrom) logDateFrom.onchange = applyPersonnelLogFilter;
  if (logDateTo) logDateTo.onchange = applyPersonnelLogFilter;

  // Initial trigger after binding (so table is always loaded)
  applyPersonnelLogFilter();
}

function applyPersonnelLogFilter() {
  const search = document.getElementById('logSearch')?.value || "";
  const status = document.getElementById('logStatusDropdown')?.value || "";
  const sort   = document.getElementById('logSortFilter')?.value || "date_desc";
  const preset = document.getElementById('logDatePreset')?.value || "today";
  let dateFrom = document.getElementById('logDateFrom')?.value || "";
  let dateTo   = document.getElementById('logDateTo')?.value || "";

  // Calculate dateFrom/dateTo based on preset if not custom
  const now = new Date();
  if (preset === "today") {
    dateFrom = dateTo = now.toISOString().slice(0, 10);
  } else if (preset === "yesterday") {
    const d = new Date(now); d.setDate(d.getDate() - 1);
    dateFrom = dateTo = d.toISOString().slice(0, 10);
  } else if (preset === "last7") {
    const d = new Date(now); d.setDate(d.getDate() - 6);
    dateFrom = d.toISOString().slice(0, 10);
    dateTo   = now.toISOString().slice(0, 10);
  } else if (preset === "last30") {
    const d = new Date(now); d.setDate(d.getDate() - 29);
    dateFrom = d.toISOString().slice(0, 10);
    dateTo   = now.toISOString().slice(0, 10);
  }
  // Custom: keep entered values from dateFrom/dateTo

  const tbody = document.querySelector('.data-table tbody');
  if (tbody) tbody.style.opacity = '0.5';

  fetch('partials/logs/search_logs.php', {
    method: 'POST',
    body: new URLSearchParams({
      search, status, sort, dateFrom, dateTo
    })
  })
  .then(res => res.text())
  .then(html => {
    if (tbody) {
      tbody.innerHTML = html;
      tbody.style.opacity = '1';
    }
  });
}


})();
