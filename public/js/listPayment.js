$(document).ready(function () {
    const token = localStorage.getItem("token");
    if(!token){ window.location.href = "index.html"; return; }

    const apiUrl = "http://localhost:8080/billing/list-Payments";

    function fetchPayments(statusFilter = "all"){
        $.ajax({
            url: apiUrl,
            method: "GET",
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
            let statusClass = "";
            if(payment.status === "pending"){
                actions = `<button class="btn btn-complete" onclick="window.location.href='makePayment.html?billingId=${payment.billing_id}'">Complete</button>
                           <button class="btn btn-cancel" onclick="handleAction(${payment.billing_id}, 'cancelled')">Cancel</button>`;
            } else if(payment.status === "completed"){
                actions = `<span class="status-completed">Completed</span>`;
                statusClass = "status-completed";
            } else {
                actions = `<span class="status-cancelled">Cancelled</span>`;
                statusClass = "status-cancelled";
            }

            let row = `<tr class="main-row" data-billing-id="${payment.billing_id}">
                <td>${payment.billing_id}</td>
                <td>${payment.appointment_id}</td>
                <td>${payment.HospitalName}</td>
                <td>${payment.patient_name}</td>
                <td>${payment.patient_email}</td>
                <td>₹ ${payment.total_amount}</td>
                <td class="${statusClass}">${payment.status}</td>
                <td>${actions}</td>
            </tr>`;

            let extraServicesHtml = "";
            if(payment.extra_services && payment.extra_services.length){
                extraServicesHtml += '<div class="extra-services"><table><tr><th>Service</th><th>Amount (₹)</th></tr>';
                payment.extra_services.forEach(s => {
                    extraServicesHtml += `<tr><td>${s.service_name}</td><td>${s.amount}</td></tr>`;
                });
                extraServicesHtml += '</table></div>';
            }

            let expandRow = `<tr class="expand-row" data-billing-id="${payment.billing_id}">
                <td colspan="8">
                    <strong>Extra Services:</strong>
                    ${extraServicesHtml}
                    ${payment.status === "completed" ? 
                        `<button class="btn btn-complete" onclick="downloadBill(${payment.billing_id})" style="margin-top:8px;">Download Bill</button>` : ""}
                </td>
            </tr>`;

            tbody.append(row);
            if(payment.status === "completed") tbody.append(expandRow);
        });

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

    // 🌟 Beautifully Styled PDF Generation
    window.downloadBill = function(billingId){
        const row = $(`.main-row[data-billing-id='${billingId}']`);
        const expandRow = $(`.expand-row[data-billing-id='${billingId}']`);
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF('p','mm','a4');
        let y = 20;

        // Header Section
        doc.setFillColor(230, 242, 255);
        doc.rect(0, 0, 210, 30, 'F');
        doc.setFontSize(18);
        doc.setTextColor(10, 102, 194);
        doc.text(`${row.find('td:eq(2)').text()}`, 14, 18);
        doc.setFontSize(10);
        doc.setTextColor(60, 60, 60);
        doc.text("Hospital Billing Receipt", 14, 26);

        y = 40;

        // Info Section
        doc.setFontSize(12);
        doc.setTextColor(0, 0, 0);
        doc.text(`Billing ID: ${row.find('td:eq(0)').text()}`, 14, y); y+=7;
        doc.text(`Appointment ID: ${row.find('td:eq(1)').text()}`, 14, y); y+=7;
        doc.text(`Patient: ${row.find('td:eq(3)').text()}`, 14, y); y+=7;
        doc.text(`Email: ${row.find('td:eq(4)').text()}`, 14, y); y+=7;
        doc.text(`Total Amount: ${row.find('td:eq(5)').text()}`, 14, y); y+=10;

        // Extra Services Table
        if(expandRow.find(".extra-services table tr").length > 1){
            doc.setFillColor(240, 248, 255);
            doc.rect(10, y, 190, 8, 'F');
            doc.setFontSize(13);
            doc.text("Charges Summary", 14, y+6);
            y += 12;
            doc.setFontSize(11);
            expandRow.find("tr").each(function(index){
                if(index===0) return;
                let service = $(this).find('td:eq(0)').text();
                let price = $(this).find('td:eq(1)').text();
                doc.text(`${service}`, 14, y);
                doc.text(`₹ ${price}`, 180, y, null, null, "right");
                y+=7;
            });
        }

        y += 10;
        doc.setDrawColor(200,200,200);
        doc.line(10, y, 200, y);
        y += 10;

        doc.setFontSize(11);
        doc.setTextColor(100, 100, 100);
        doc.text("Thank you for choosing our hospital!", 14, y);
        y += 6;
        doc.setFontSize(9);
        doc.text("Generated by Hospital Management System", 105, 290, null, null, "center");

        doc.save(`Bill_${row.find('td:eq(0)').text()}.pdf`);
    }

    $("#statusFilter").change(function(){
        fetchPayments($(this).val());
    });

    fetchPayments();
});
