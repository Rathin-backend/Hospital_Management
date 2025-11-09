$(document).ready(function () {

  const token = localStorage.getItem("token");
  const role = localStorage.getItem("role"); // 0 admin, 1 doctor, 2 patient, 3 superadmin
  const hospitalId = localStorage.getItem("hospital_id");

  if (!token) return location.href = "index.html";

  // Patient cannot access this page
  if (role === "2") {
    alert("Access Denied!");
    return location.href = "dashboard.html";
  }

  // Show filter only for SuperAdmin
  if (role === "3") {
    $("#hospitalFilterRow").show();

    $.ajax({
      url:"http://localhost:8080/hospital/list-all-Hospitals",
      headers:{ Authorization:`Bearer ${token}` },
      success:res=>{
        res.data?.forEach(h=>{
          $("#filterHospital").append(`<option value="${h.id}">${h.name}</option>`);
        });
      }
    });
  }

  function loadPatients() {
    let params = {};

    // SA can filter by hospital
    if (role === "3" && $("#filterHospital").val()) {
      params.hospital_id = $("#filterHospital").val();
    }
    // Admin and Doctor always send own hospital ID
    if (role === "0" || role === "1") {
      params.hospital_id = hospitalId;
    }

    $.ajax({
      url:"http://localhost:8080/api/list-Patients",
      method:"GET",
      data: params,
      headers:{ Authorization:`Bearer ${token}` },

      success:res=>{
        const tbody = $("#patientsTable tbody").empty();
        const list = res.data || [];

        if (!list.length) {
          return tbody.append(`<tr><td colspan="5" style="text-align:center;">No patients found</td></tr>`);
        }

        list.forEach(p=>{
          tbody.append(`
            <tr>
              <td>${p.name}</td>
              <td>${p.gender}</td>
              <td>${p.email}</td>
              <td>${p.phone_no || ""}</td>
              <td>
                <button class="btn-edit" data-id="${p.id}">Edit</button>
                <button class="btn-delete" data-id="${p.id}">Delete</button>
              </td>
            </tr>
          `);
        });
      }
    });
  }

  loadPatients();

  $("#filterHospital").change(loadPatients);

  // ✅ Edit
  $(document).on("click",".btn-edit",function(){
    const id=$(this).data("id");

    $.ajax({
      url:`http://localhost:8080/api/user/${id}`,
      headers:{Authorization:`Bearer ${token}`},
      success:res=>{
        let u=res.data;
        $("#editPatientId").val(u.id);
        $("#editName").val(u.name);
        $("#editGender").val(u.gender);
        $("#editEmail").val(u.email);
        $("#editPhone").val(u.phone_no);

        $("#editModal").css("display","flex");
      }
    });
  });

  $("#saveEditBtn").click(function(){
    $.ajax({
      url:`http://localhost:8080/api/Edit-Patient`,
      method:"POST",
      headers:{Authorization:`Bearer ${token}`},
      data:{
        patientId: $("#editPatientId").val(),
        name: $("#editName").val(),
        gender: $("#editGender").val(),
        email: $("#editEmail").val(),
        phone_no: $("#editPhone").val()
      },
      success:r=>{
        alert(r.message);
        $("#editModal").hide();
        loadPatients();
      }
    });
  });

  $("#cancelEditBtn").click(()=>$("#editModal").hide());

  // ✅ Delete
  $(document).on("click",".btn-delete",function(){
    const id=$(this).data("id");

    if(!confirm("Delete this patient?")) return;

    $.ajax({
      url:"http://localhost:8080/api/Delete-Patient",
      method:"POST",
      headers:{Authorization:`Bearer ${token}`},
      data:{ patientId:id, _method:"DELETE" },
      success:r=>{
        alert(r.message);
        loadPatients();
      }
    });
  });

  $("#addPatientBtn").click(()=>location.href="addPatient.html");
  $("#backBtn").click(()=>location.href="dashboard.html");

});
