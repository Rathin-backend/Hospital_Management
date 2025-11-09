$(document).ready(function () {

const token = localStorage.getItem("token");
const role = localStorage.getItem("role"); 
const hospitalId = localStorage.getItem("hospital_id");
if (!token) return location.href = "index.html";

$("#backBtn").click(()=>location.href="dashboard.html");
$("#bookAppointmentBtn").click(()=>location.href="bookAppointment.html");

// Book button only for patient
if (role !== "2") $("#bookAppointmentBtn").hide();
if (role === "3") $("#filterHospital").show();

// Load hospital dropdown for SA
if (role === "3") {
  $.ajax({
    url:"http://localhost:8080/hospital/list-all-Hospitals",
    headers:{ Authorization:`Bearer ${token}` },
    success:res=>{
      res.data?.forEach(h=>$("#filterHospital").append(`<option value="${h.id}">${h.name}</option>`));
    }
  });
}

// Table header
if (role === "2")
  $("#appointmentsHeader").html(`<th>ID</th><th>Doctor</th><th>Patient</th><th>Hospital</th><th>Date</th><th>Start</th><th>End</th><th>Status</th>`);
else
  $("#appointmentsHeader").html(`<th>ID</th><th>Doctor</th><th>Patient</th><th>Hospital</th><th>Date</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th>`);

function t12(t){ if(!t) return ""; let [h,m]=t.split(":"); h=+h; return `${h%12||12}:${m} ${h>=12?"PM":"AM"}`; }

function loadAppointments(){
  const q = {
    search:$("#searchBox").val(),
    appointmentId:$("#filterAppointmentId").val(),
    date:$("#filterDate").val(),
    status:$("#filterStatus").val(),
    dateFilter:$("#filterRange").val(),
    hospital_id: role==="3" ? $("#filterHospital").val() : hospitalId
  };

  $.ajax({
    url:"http://localhost:8080/appointment/List-appointments",
    data:q,
    headers:{ Authorization:`Bearer ${token}` },
    success:res=>{
      const tbody=$("#appointmentsTable tbody").empty();
      if(!res.data?.length) return tbody.append(`<tr><td colspan="10" style="text-align:center;">No appointments found</td></tr>`);

      res.data.forEach(a=>{
        const st=(a.status||"").toLowerCase();
        let row=`<tr class="appt-row">
          <td>${a.id}</td>
          <td>${a.doctor_name||"-"}</td>
          <td>${a.patient_name||"-"}</td>
          <td>${a.hospital_name||"-"}</td>
          <td>${a.Appointment_date}</td>
          <td>${t12(a.Appointment_startTime)}</td>
          <td>${t12(a.Appointment_endTime)}</td>
          <td><span class="status ${st}">${a.status}</span></td>`;

        if(role!=="2"){
          let btns="";
if(st === "completed")
  btns = `<span class="status completed">${a.status}</span>`;
else if(st === "cancelled")
  btns = `<span class="status cancelled">${a.status}</span>`;
else if(st === "rescheduled")
  btns = `<span class="status rescheduled">${a.status}</span>`;
else if(st==="pending"){
  if(role==="1") btns+=`<button class="btn-confirm" data-id="${a.id}">Confirm</button>`;
  btns+=`<button class="btn-reschedule" data-id="${a.id}" data-date="${a.Appointment_date}" data-time="${a.Appointment_startTime}">Reschedule</button>
        <button class="btn-cancel" data-id="${a.id}">Cancel</button>`;
}
else if(st==="booked"){
  btns+=`<button class="btn-complete" data-id="${a.id}">Complete</button>
         <button class="btn-reschedule" data-id="${a.id}" data-date="${a.Appointment_date}" data-time="${a.Appointment_startTime}">Reschedule</button>
         <button class="btn-cancel" data-id="${a.id}">Cancel</button>`;
}

          row+=`<td>${btns}</td>`;
        }

        row+="</tr>";
        tbody.append(row);
      });
    }
  });
}

loadAppointments();
$("#searchBox,#filterDate,#filterStatus,#filterRange,#filterAppointmentId,#filterHospital").on("input change",loadAppointments);

// Confirm
$(document).on("click",".btn-confirm",function(e){
 e.stopPropagation();
 const id=$(this).data("id");
 $.post({
   url:"http://localhost:8080/appointment/confirm-Appointment",
   headers:{Authorization:`Bearer ${token}`},
   data:{appointment_id:id},
   success:r=>{alert(r.Mssge);loadAppointments();}
 });
});

// Complete
$(document).on("click",".btn-complete",function(e){
 e.stopPropagation();
 location.href=`completeAppointment.html?appointmentId=${$(this).data("id")}`;
});

// Cancel
$(document).on("click",".btn-cancel",function(e){
 e.stopPropagation();
 const id=$(this).data("id");
 const reason=prompt("Cancel reason:");
 if(!reason) return;
 $.post({
   url:"http://localhost:8080/api/cancel-Appointment",
   headers:{Authorization:`Bearer ${token}`},
   data:{appointmentId:id,cancel_reason:reason},
   success:r=>{alert(r.message);loadAppointments();}
 });
});

// Reschedule
$(document).on("click",".btn-reschedule",function(e){
 e.stopPropagation();
 $("#rescheduleAppointmentId").val($(this).data("id"));
 $("#rescheduleDate").val($(this).data("date"));
 $("#rescheduleTime").val($(this).data("time"));
 $("#rescheduleModal").show();
});

$("#rescheduleForm").submit(e=>{
 e.preventDefault();
 $.post({
   url:"http://localhost:8080/appointment/Reschedule-appointment",
   headers:{Authorization:`Bearer ${token}`},
   data:{
     appointment_id:$("#rescheduleAppointmentId").val(),
     newAppointmentDate:$("#rescheduleDate").val(),
     newAppointmentstartTime:$("#rescheduleTime").val(),
     reschedule_reason:$("#rescheduleReason").val()
   },
   success:r=>{alert(r.message);$("#rescheduleModal").hide();loadAppointments();}
 });
});

// ✅ Expand row to show appointment logs
$(document).on("click",".appt-row",function(){
 const row = $(this);
 const appointmentId = row.find("td:first").text();

 if (row.next().hasClass("log-row")) {
   row.next().remove();
   return;
 }

 $(".log-row").remove(); 

 const logRow = $("<tr class='log-row'></tr>");
 logRow.html($("#logTemplate").html());
 row.after(logRow);

 const box = logRow.find(".timeline-box").html("<b>Loading timeline...</b>");

 $.ajax({
   url:`http://localhost:8080/appointment/Get-Appointment-Logs?appointment_id=${appointmentId}`,
   headers:{ Authorization:`Bearer ${token}` },
   success:res=>{
     if(!res.status || !res.data?.length){
       box.html("<i>No activity history available</i>");
       return;
     }

     let html="";
     res.data.forEach(log=>{
       html+=`
       <div class="timeline-item">
         <div class="timeline-action">🕘 ${log.action?.toUpperCase()}</div>
         <div class="timeline-time">${log.created_at||''}</div>
         ${log.old_date?`<div><b>Old:</b> ${log.old_date} ${log.old_start_time}</div>`:""}
         ${log.new_date?`<div><b>New:</b> ${log.new_date} ${log.new_start_time}</div>`:""}
         ${log.reason?`<div><b>Reason:</b> ${log.reason}</div>`:""}
       </div>`;
     });

     box.html(html);
   }
 });
});

// Export CSV
$("#exportCSV").click(()=>{
 const params=new URLSearchParams({
   search:$("#searchBox").val(),
   date:$("#filterDate").val(),
   status:$("#filterStatus").val(),
   dateFilter:$("#filterRange").val(),
   appointmentId:$("#filterAppointmentId").val(),
   hospital_id: role==="3"?$("#filterHospital").val():hospitalId
 }).toString();

 window.open(`http://localhost:8080/appointment/export-csv?${params}`);
});

});
