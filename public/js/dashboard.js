$(function () {
  const API = "http://localhost:8080";
  const token = localStorage.getItem("token");
  const role = localStorage.getItem("role");         // 0=Admin, 1=Doctor, 2=Patient, 3=SuperAdmin
  const userName = localStorage.getItem("userName") || "User";
  const hospitalId = localStorage.getItem("hospital_id");

  // ----- Auth guard -----
  if (!token) {
    window.location.href = "index.html";
    return;
  }

  $("#userWelcome").text(`Welcome, ${userName}`);

  // ----- Role-based menus + sections -----
  (function setupMenus() {
    // hide all first
    $("#addDoctorMenu, #addPatientMenu, #doctorsMenu, #patientsMenu, #appointmentsMenu, #bookAppointmentMenu, #listPaymentMenu, #addHospitalMenu, #addAdminMenu, #AdminsMenu, #HospitalMenu, #showHistory").addClass("hidden");
    $("#dashboardSection").show();
    $("#patientDashboardSection").hide();

    if (role === "0") { // Admin
      $("#addDoctorMenu, #addPatientMenu, #doctorsMenu, #patientsMenu, #appointmentsMenu, #bookAppointmentMenu, #listPaymentMenu").removeClass("hidden");
      loadStats();
      loadHospitalName();
    } else if (role === "1") { // Doctor
      $("#patientsMenu, #appointmentsMenu, #showHistory").removeClass("hidden");
      loadStats();
      loadHospitalName();
    } else if (role === "2") { // Patient
      $("#bookAppointmentMenu, #appointmentsMenu, #showHistory, #listPaymentMenu").removeClass("hidden");
      $("#dashboardSection").hide();
      $("#patientDashboardSection").show();
      loadPatientDetails();
      loadPatientCharts();
      loadHistory(); // in dashboard itself
    } else if (role === "3") { // SuperAdmin
      $("#addHospitalMenu, #doctorsMenu, #patientsMenu, #appointmentsMenu, #addAdminMenu, #AdminsMenu").removeClass("hidden");
      loadStats();
    }
  })();

  // ----- Stats cards -----
  function loadStats() {
    $.ajax({
      url: `${API}/api/dashboard/stats`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: (res) => {
        const $c = $("#statsCards").empty();
        if (typeof res.doctors !== "undefined") $c.append(card("Doctors", res.doctors, "fa-user-doctor"));
        if (typeof res.patients !== "undefined") $c.append(card("Patients", res.patients, "fa-users"));
        if (typeof res.appointments !== "undefined") $c.append(card("Appointments", res.appointments, "fa-calendar-check"));
        if (typeof res.hospitals !== "undefined") $c.append(card("Hospitals", res.hospitals, "fa-hospital"));
      },
      error: () => console.warn("Failed to fetch stats")
    });
  }
  function card(title, number, icon) {
    return `<div class="stat-card">
              <div class="icon"><i class="fas ${icon}"></i></div>
              <h3>${title}</h3>
              <div class="number">${number}</div>
            </div>`;
  }

  // ----- Hospital banner -----
  function loadHospitalName() {
    if (!hospitalId) return;
    $.ajax({
      url: `${API}/hospital/get-Hospital-Info`,
      method: "GET",
      data: { hospital_id: hospitalId },
      headers: { Authorization: `Bearer ${token}` },
      success: (res) => {
        const name = res?.data?.name || "Hospital";
        $("#hospitalNameAnimated").text(`Welcome to ${name}`);
      },
      error: () => $("#hospitalNameAnimated").text("Welcome")
    });
  }

  // ----- Patient: Basic details -----
  function loadPatientDetails() {
    $.ajax({
      url: `${API}/appointment/getDetailsforPatient`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: (res) => {
        const d = res?.data || {};
        $("#pName").text(d.name || "-");
        $("#pEmail").text(d.email || "-");
        $("#pGender").text(d.gender || "-");
        $("#pDOB").text(d.DOB || "-");
        $("#pPhoto").attr("src", d.photo || "https://via.placeholder.com/100");
      }
    });
  }

  // ----- Patient: Charts (weight + BP) -----
  function loadPatientCharts() {
    $.ajax({
      url: `${API}/appointment/getPatientStats`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: (res) => {
        if (!res?.status || !Array.isArray(res.data)) return;
        const labels = res.data.map(x => x.date);
        const weights = res.data.map(x => Number(x.weight || 0));
        const sys = res.data.map(x => Number(x.bp_systolic || 0));
        const dia = res.data.map(x => Number(x.bp_diastolic || 0));
        drawLine("weightChart", "Weight (kg)", labels, weights, "#2a6bc9");
        drawMultiLine("bpChart", labels,
          { label: "Systolic", data: sys, color: "#e53e3e" },
          { label: "Diastolic", data: dia, color: "#38a169" }
        );
      }
    });
  }
  function drawLine(canvasId, label, labels, data, color) {
    new Chart(document.getElementById(canvasId), {
      type: "line",
      data: { labels, datasets: [{ label, data, borderColor: color, backgroundColor: `${color}33`, fill: true, tension: 0.3 }] },
      options: { responsive: true }
    });
  }
  function drawMultiLine(canvasId, labels, s1, s2) {
    new Chart(document.getElementById(canvasId), {
      type: "line",
      data: {
        labels,
        datasets: [
          { label: s1.label, data: s1.data, borderColor: s1.color, backgroundColor: `${s1.color}33`, fill: true, tension: 0.3 },
          { label: s2.label, data: s2.data, borderColor: s2.color, backgroundColor: `${s2.color}33`, fill: true, tension: 0.3 }
        ]
      },
      options: { responsive: true }
    });
  }

  // ----- Patient: Visit history (expand to show all 4 tables + Rx download) -----
  function loadHistory() {
    $.ajax({
      url: `${API}/appointment/show-History`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: (res) => {
        const $tb = $("#historyTableBody").empty();

        if (!res?.status || !Array.isArray(res.data) || !res.data.length) {
          $tb.append(`<tr><td colspan="8" class="text-center text-muted">No history available</td></tr>`);
          return;
        }

        res.data.forEach((row, idx) => {
          const detailsId = `details-${idx}`;

          // Top row
          const trMain = `
            <tr class="main-row" data-target="${detailsId}">
              <td>${row.appointment_id}</td>
              <td>${row?.doctor?.name || "-"}</td>
              <td>${row?.patient?.name || "-"}</td>
              <td>${row?.hospital?.name || "-"}</td>
              <td>${row.appointment_date || "-"}</td>
              <td>${row.appointment_startTime || "-"}</td>
              <td>${row.appointment_endTime || "-"}</td>
              <td><span class="badge bg-success">completed</span></td>
            </tr>`;

          // Details row with all 4 tables
          const v = row?.visit_details || {};
          const complaintsHTML = (v.complaints || []).map(c =>
            `<tr><td>${c.complaint || "-"}</td><td>${c.description || "-"}</td><td>${c.severity || "-"}</td><td>${c.days || "-"}</td></tr>`
          ).join("") || `<tr><td colspan="4" class="text-muted text-center">No complaints</td></tr>`;

          const diagnosesHTML = (v.diagnoses || []).map(d =>
            `<tr><td>${d.diagnosis_name || "-"}</td><td>${d.notes || "-"}</td></tr>`
          ).join("") || `<tr><td colspan="2" class="text-muted text-center">No diagnoses</td></tr>`;

          const prescriptionsHTML = (v.prescriptions || []).map((p, i) => `
            <tr>
              <td>${p.medicine_name || "-"}</td>
              <td>${p.dosage || "-"}</td>
              <td>${p.frequency || "-"}</td>
              <td>${p.duration || "-"}</td>
              <td>${p.instructions || "-"}</td>
              <td><button class="btn btn-sm btn-primary downloadRxBtn" 
                    data-idx="${i}" data-parent="${detailsId}">
                    <i class="fa-solid fa-file-pdf"></i> Download
                  </button></td>
            </tr>
          `).join("") || `<tr><td colspan="6" class="text-muted text-center">No prescriptions</td></tr>`;

          const trDetails = `
            <tr id="${detailsId}" class="details-row" style="display:none;">
              <td colspan="8">
                <div class="details-box">
                  <div class="row">
                    <div class="col-md-6">
                      <h5>Visit Details</h5>
                      <p><b>Visit ID:</b> ${v.visit_id || "-"}</p>
                      <p><b>Date:</b> ${v.date || "-"}</p>
                      <p><b>Weight:</b> ${v.weight || "-"} kg</p>
                      <p><b>BP:</b> ${v.bp_systolic || "-"} / ${v.bp_diastolic || "-"}</p>
                      <p><b>Doctor Comment:</b> ${v.doctor_comment || "-"}</p>
                    </div>
                    <div class="col-md-6">
                      <h5>Hospital</h5>
                      <p><b>Name:</b> ${row?.hospital?.name || "-"}</p>
                      <p><b>Contact:</b> ${row?.hospital?.contact || "-"}</p>
                      <p><b>Address:</b> ${row?.hospital?.address || "-"}</p>
                    </div>
                  </div>

                  <hr/>
                  <h5>Complaints</h5>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                      <thead class="table-light"><tr><th>Complaint</th><th>Description</th><th>Severity</th><th>Days</th></tr></thead>
                      <tbody>${complaintsHTML}</tbody>
                    </table>
                  </div>

                  <h5>Diagnoses</h5>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                      <thead class="table-light"><tr><th>Diagnosis</th><th>Notes</th></tr></thead>
                      <tbody>${diagnosesHTML}</tbody>
                    </table>
                  </div>

                  <h5>Prescriptions</h5>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                      <thead class="table-light">
                        <tr>
                          <th>Medicine</th><th>Dosage</th><th>Frequency</th>
                          <th>Duration</th><th>Instructions</th><th>Action</th>
                        </tr>
                      </thead>
                      <tbody>${prescriptionsHTML}</tbody>
                    </table>
                  </div>
                </div>
              </td>
            </tr>`;

          $tb.append(trMain + trDetails);

          // attach original object to details row for PDF extraction later
          $(`#${detailsId}`).data("record", row);
        });

        // expand/collapse
        $(".main-row").off("click").on("click", function () {
          const targetId = $(this).data("target");
          $("#" + targetId).toggle();
        });

        // per-prescription PDF
        $(".downloadRxBtn").off("click").on("click", function (e) {
          e.stopPropagation();
          const detailsId = $(this).data("parent");
          const rxIndex = Number($(this).data("idx"));
          const record = $("#" + detailsId).data("record");
          downloadPrescriptionPDF(record, rxIndex);
        });
      },
      error: () => {
        $("#historyTableBody").html(`<tr><td colspan="8" class="text-center text-danger">Failed to fetch history</td></tr>`);
      }
    });
  }

  function downloadPrescriptionPDF(record, rxIndex) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const v = record?.visit_details || {};
    const hospital = record?.hospital || {};
    const doctor = record?.doctor || {};
    const patient = record?.patient || {};
    const rx = (v.prescriptions || [])[rxIndex];

    // Title
    doc.setFontSize(18);
    doc.setFont("helvetica", "bold");
    doc.text("Prescription", 105, 14, { align: "center" });

    // Appointment info
    const info = [
      ["Appointment ID", record.appointment_id || "-"],
      ["Hospital", hospital.name || "-"],
      ["Doctor", doctor.name || "-"],
      ["Patient", patient.name || "-"],
      ["Date", record.appointment_date || "-"],
      ["Time", `${record.appointment_startTime || "-"} - ${record.appointment_endTime || "-"}`]
    ];
    doc.autoTable({ startY: 20, head: [["Field", "Value"]], body: info, theme: "grid" });

    // Visit summary
    const visit = [
      ["Visit ID", v.visit_id || "-"],
      ["Visit Date", v.date || "-"],
      ["Weight", (v.weight ? `${v.weight} kg` : "-")],
      ["Blood Pressure", `${v.bp_systolic || "-"} / ${v.bp_diastolic || "-"}`],
      ["Doctor Comment", v.doctor_comment || "-"]
    ];
    doc.autoTable({ startY: doc.lastAutoTable.finalY + 8, head: [["Visit", "Value"]], body: visit, theme: "grid" });

    // Prescription (single)
    if (rx) {
      const rxTable = [
        ["Medicine", rx.medicine_name || "-"],
        ["Dosage", rx.dosage || "-"],
        ["Frequency", rx.frequency || "-"],
        ["Duration", rx.duration || "-"],
        ["Instructions", rx.instructions || "-"]
      ];
      doc.autoTable({ startY: doc.lastAutoTable.finalY + 8, head: [["Prescription Field", "Value"]], body: rxTable, theme: "grid" });
    }

    // Footer
    doc.setFontSize(10);
    doc.text("Generated by Smart Hospital Portal", 105, doc.internal.pageSize.height - 10, { align: "center" });

    doc.save(`Prescription_${record.appointment_id}_${rxIndex + 1}.pdf`);
  }

  // ----- Profile: Prefill + Save (uses your routes: GET /auth/user/:id, POST /auth/update-profile) -----
  $("#editProfileBtn").on("click", function () {
    $("#editProfileForm").slideDown();
    // Route demands :id but your controller uses token -> safe to pass 0
    $.ajax({
      url: `${API}/auth/user/0`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: function (res) {
        const u = res?.data || {};
        $("#editName").val(u.name || "");
        $("#editEmail").val(u.email || "");
        $("#editGender").val(u.gender || "male");
        $("#editPassword").val("");
        // role-specific extra field (doctor expertise only)
        if (role === "1") {
          $("#roleSpecificField").html(`<label>Expertise</label><input type="text" id="editExpertise" class="form-control" value="${u.expertise || ""}">`);
        } else {
          $("#roleSpecificField").empty();
        }
      },
      error: function () {
        alert("Failed to load profile");
      }
    });
  });

  $("#cancelEditProfile").on("click", function () {
    $("#editProfileForm").slideUp();
  });

  $("#editProfileForm").on("submit", function (e) {
    e.preventDefault();
    const payload = {
      name: $("#editName").val(),
      email: $("#editEmail").val(),
      gender: $("#editGender").val()
    };
    const pw = $("#editPassword").val();
    if (pw) payload.password = pw;
    if (role === "1") payload.expertise = $("#editExpertise").val();

    $.ajax({
      url: `${API}/auth/update-profile`,
      method: "POST", // per your routes file
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
      data: JSON.stringify(payload),
      success: function (res) {
        if (res?.status) {
          alert(res?.message || "Profile updated");
          $("#editProfileForm").slideUp();
          if (res?.data?.name) {
            localStorage.setItem("userName", res.data.name);
            $("#userWelcome").text(`Welcome, ${res.data.name}`);
          }
        } else {
          alert(res?.message || "Failed to update profile");
        }
      },
      error: function () { alert("Error updating profile"); }
    });
  });

  // ----- Logout -----
  $("#logoutBtn").on("click", function () {
    localStorage.clear();
    window.location.href = "index.html";
  });
});
