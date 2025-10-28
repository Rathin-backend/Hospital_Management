$(document).ready(function () {
  const token = localStorage.getItem("token");
  const role = localStorage.getItem("role");
  const apiBase = "http://localhost:8080";

  let selectedDate = null;
  let selectedSlot = null;

  if (!token) {
    alert("Login required");  
    window.location.href = "dashboard.html";
    return;
  }

  if (role !== "2") {
    $("#bookForm input, #bookForm select, #bookForm button").prop("disabled", true);
    $("#responseMessage").addClass("error").text("⚠️ Only patients can book appointments.");
  }

  // === Load Hospitals ===
  function loadHospitals() {
    $.ajax({
      url: `${apiBase}/hospital/list-all-Hospitals`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      success: function (res) {
        $("#hospitalSelect").empty().append(`<option value="">-- Select Hospital --</option>`);
        (res.data || []).forEach(h => {
          $("#hospitalSelect").append(`<option value="${h.id}">${h.name}</option>`);
        });
      },
      error: function () {
        $("#responseMessage").addClass("error").text("Failed to load hospitals.");
      }
    });
  }

  // === Load Doctors ===
  function loadDoctors(hospitalId, department = "all") {
    if (!hospitalId) return;
    $.ajax({
      url: `${apiBase}/api/list-Doctors-Hospital-Wise`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      data: { hospital_id: hospitalId },
      success: function (res) {
        $("#doctorSelect").empty().append(`<option value="">-- Select Doctor --</option>`);
        (res.data || []).forEach(doc => {
          if (department === "all" || (doc.expertise && doc.expertise.toLowerCase().includes(department.toLowerCase()))) {
            $("#doctorSelect").append(`<option value="${doc.id}">${doc.name} (${doc.expertise || "N/A"})</option>`);
          }
        });
      },
      error: function () {
        $("#responseMessage").addClass("error").text("Failed to load doctors.");
      }
    });
  }

  // === Generate Next 14 Days (exclude Sundays) ===
  function generateDates() {
    const strip = $("#dateStrip").empty();
    const today = new Date();
    let count = 0;
    while (count < 14) {
      today.setDate(today.getDate() + 1);
      const day = today.getDay();
      if (day === 0) continue; // skip Sunday
      const dateStr = today.toISOString().split("T")[0];
      const dayShort = today.toLocaleDateString("en-US", { weekday: "short" });
      const dateNum = today.getDate();
      strip.append(`
        <div class="date-card" data-date="${dateStr}">
          <div>${dayShort}</div>
          <div>${dateNum}</div>
        </div>
      `);
      count++;
    }
  }

  // === Fetch Doctor Availability ===
  function fetchAvailability(hospitalId, doctorId, date) {
    if (!hospitalId || !doctorId || !date) return;
    $.ajax({
      url: `${apiBase}/appointment/get-Doctor-Availability`,
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
      data: { hospital_id: hospitalId, DoctorId: doctorId, Date: date },
      success: function (res) {
        const slotsContainer = $("#slotsContainer").empty();
        const slots = res.data || [];
        if (slots.length === 0) {
          slotsContainer.html("<p style='color:#6b7280'>No slots available</p>");
          return;
        }

        // ✅ Show slots in AM/PM format and store AM/PM value in data attribute
        slots.forEach(time => {
          const t12 = convertTo12Hour(time);
          slotsContainer.append(`<div class="slot-card" data-time="${t12}">${t12}</div>`);
        });
      },
      error: function (xhr) {
        console.error("Error fetching slots:", xhr.responseText);
      }
    });
  }

  // === Convert HH:mm:ss → 12-hour format ===
  function convertTo12Hour(timeStr) {
    const [h, m] = timeStr.split(":");
    let hour = parseInt(h, 10);
    const ampm = hour >= 12 ? "PM" : "AM";
    hour = hour % 12 || 12;
    return `${hour}:${m} ${ampm}`;
  }

  // === Event Handlers ===
  $("#hospitalSelect").change(function () {
    const hospitalId = $(this).val();
    const dept = $("#departmentSelect").val();
    loadDoctors(hospitalId, dept);
  });

  $("#departmentSelect").change(function () {
    const hospitalId = $("#hospitalSelect").val();
    loadDoctors(hospitalId, $(this).val());
  });

  $("#doctorSelect").change(function () {
    const hospitalId = $("#hospitalSelect").val();
    const doctorId = $(this).val();
    if (selectedDate) fetchAvailability(hospitalId, doctorId, selectedDate);
  });

  $(document).on("click", ".date-card", function () {
    $(".date-card").removeClass("selected");
    $(this).addClass("selected");
    selectedDate = $(this).data("date");
    const hospitalId = $("#hospitalSelect").val();
    const doctorId = $("#doctorSelect").val();
    if (hospitalId && doctorId) fetchAvailability(hospitalId, doctorId, selectedDate);
  });

  $(document).on("click", ".slot-card", function () {
    $(".slot-card").removeClass("selected");
    $(this).addClass("selected");
    selectedSlot = $(this).data("time"); // ✅ This now stores AM/PM format
  });

  // === Submit Appointment ===
  $("#bookForm").submit(function (e) {
    e.preventDefault();
    const hospital_id = $("#hospitalSelect").val();
    const doctorId = $("#doctorSelect").val();

    if (!hospital_id || !doctorId || !selectedDate || !selectedSlot) {
      $("#responseMessage").addClass("error").text("Please select all fields.");
      return;
    }

    console.log("Booking payload →", {
      hospital_id,
      doctorId,
      appointment_date: selectedDate,
      appointment_startTime: selectedSlot
    });

    $.ajax({
      url: `${apiBase}/appointment/Book-appointment`,
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
      data: { hospital_id, doctorId, appointment_date: selectedDate, appointment_startTime: selectedSlot },
      success: function (res) {
        if (res.status) {
          $("#responseMessage").removeClass("error").addClass("success").text(res.mssge);
          setTimeout(() => window.location.href = "appointments.html", 2000);
        } else {
          $("#responseMessage").addClass("error").text(res.mssge || res.Error || "Booking failed.");
        }
      },
      error: function (xhr) {
        $("#responseMessage").addClass("error").text("Error: " + xhr.responseText);
      }
    });
  });

  $("#backBtn").click(() => window.location.href = "dashboard.html");

  // === Init ===
  if (role === "2") {
    loadHospitals();
    generateDates();
  }
});
