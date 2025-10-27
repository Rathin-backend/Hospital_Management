$(document).ready(function () {
    const token = localStorage.getItem("token");
    if(!token){ window.location.href = "index.html"; return; }

    const apiUrl = "http://localhost:8080/billing/list-Payments";

    function fetchPayments(statusFilter = "all"){
        $.ajax({
            url: apiUrl,
            method: "POST",
            headers: { Authorization: `Bearer ${token}` },
            data: {},
            success: function(res){
                if(res.status){
                    renderTable(res.data, statusFilter);
                } else {
                    alert(res.message || "Failed to fetch payments");
                }
            },
            error: function(err){ console.error(err); alert("Error fetching payments"); }
        });
    }

    function renderTable(data, statusFilter){
        let tbody = $("#paymentsTable tbody");
        tbody.empty();
        data.forEach(payment => {
            if(statusFilter !== "all" && payment.status !== statusFilter) return;

            let actions = "";
            if(payment.status === "pending"){
                actions = `<button class="btn btn-complete" onclick="window.location.href='makePayment.html?billingId=${payment.billing_id}'">Complete</button>
                           <button class="btn btn-cancel" onclick="handleAction(${payment.billing_id}, 'cancelled')">Cancel</button>`;
            } else if(payment.status === "completed"){
                actions = `<span class="badge-completed">Completed</span>`;
            } else {
                actions = `<span class="badge-cancelled">Cancelled</span>`;
            }

            let row = `<tr class="main-row" data-billing-id="${payment.billing_id}">
                <td>${payment.billing_id}</td>
                <td>${payment.appointment_id}</td>
                <td>${payment.patient_name}</td>
                <td>${payment.patient_email}</td>
                <td>₹ ${payment.total_amount}</td>
                <td>${payment.status}</td>
                <td>${actions}</td>
            </tr>`;

            // Extra services table
            let extraServicesHtml = "";
            if(payment.extra_services && payment.extra_services.length){
                extraServicesHtml += '<div class="extra-services"><table><tr><th>Service</th><th>Amount (₹)</th></tr>';
                payment.extra_services.forEach(s => {
                    extraServicesHtml += `<tr><td>${s.service_name}</td><td>${s.amount}</td></tr>`;
                });
                extraServicesHtml += '</table></div>';
            }

            let expandRow = `<tr class="expand-row" data-billing-id="${payment.billing_id}">
                <td colspan="7">
                    <strong>Extra Services:</strong>
                    ${extraServicesHtml}
                    ${payment.extra_services && payment.extra_services.length ? 
                        `<button class="btn btn-complete" onclick="downloadBill(${payment.billing_id})" style="margin-top:8px;">Download Bill</button>` : ""}
                </td>
            </tr>`;

            tbody.append(row);
            if(payment.status === "completed") tbody.append(expandRow);
        });

        // Toggle expand on completed row click
        $(".main-row").click(function(){
            let billingId = $(this).data("billing-id");
            let expand = $(`.expand-row[data-billing-id='${billingId}']`);
            if(expand.length) expand.toggle();
        });
    }

    window.handleAction = function(billingId, newStatus){
        if(!confirm(`Are you sure to mark this payment as ${newStatus}?`)) return;

        $.ajax({
            url: `http://localhost:8080/billing/update-status/${billingId}`,
            method: "POST",
            headers: { Authorization: `Bearer ${token}` },
            data: { status: newStatus },
            success: function(res){
                if(res.status){
                    alert("Updated successfully");
                    fetchPayments($("#statusFilter").val());
                } else { alert(res.message || "Failed to update"); }
            },
            error: function(err){ console.error(err); alert("Error updating"); }
        });
    }

    window.downloadBill = function(billingId){
        const row = $(`.main-row[data-billing-id='${billingId}']`);
        const expandRow = $(`.expand-row[data-billing-id='${billingId}']`);
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF('p','mm','a4');

        let y = 15;
        doc.setFontSize(18); doc.text("🏥 Hospital Bill", 105, y, null, null, "center"); y+=10;
        doc.setFontSize(12); doc.text(`Billing ID: ${row.find('td:eq(0)').text()}`, 14, y); y+=6;
        doc.text(`Appointment ID: ${row.find('td:eq(1)').text()}`, 14, y); y+=6;
        doc.text(`Patient: ${row.find('td:eq(2)').text()} (${row.find('td:eq(3)').text()})`, 14, y); y+=8;
        doc.text(`Total Amount: ${row.find('td:eq(4)').text()}`, 14, y); y+=8;

        if(expandRow.find(".extra-services table tr").length > 1){
            doc.setFontSize(14); doc.text("Extra Services", 14, y); y+=6;
            doc.setFontSize(12);
            expandRow.find("tr").each(function(index){
                if(index===0) return; // skip header
                let service = $(this).find('td:eq(0)').text();
                let price = $(this).find('td:eq(1)').text();
                doc.text(`${service} : ₹ ${price}`, 14, y); y+=6;
            });
        }

        doc.setFontSize(10);
        doc.text("Generated by Hospital Management System", 105, 290, null, null, "center");

        doc.save(`Billing_${row.find('td:eq(0)').text()}.pdf`);
    }

    // Filter
    $("#statusFilter").change(function(){
        fetchPayments($(this).val());
    });

    // Initial fetch
    fetchPayments();
});
