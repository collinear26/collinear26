<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center | ASCOT RecordsHub</title>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- External Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <!-- Anti-Flicker Script -->
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    </script>
    <style>
        /* Interactive styles para sa clickable help cards at modal */
        .help-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .help-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
            border-color: rgba(6, 78, 59, 0.4);
        }

        /* FAQ Accordion */
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            padding-left: 24px;
        }
        .faq-item.active .faq-answer {
            max-height: 150px;
            padding-top: 6px;
            padding-bottom: 6px;
        }
        .faq-question {
            cursor: pointer;
            user-select: none;
        }
        .faq-question:hover {
            color: #064e3b;
        }

        /* Modal Overlay Style */
        .help-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .help-modal.active {
            display: flex;
        }
        .help-modal-content {
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }
        .help-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            cursor: pointer;
            color: #64748b;
        }
        .help-modal-close:hover {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="app-container">
        
        <!-- CENTRALIZED SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>Help & Support Center</h1>
                    <p>Find answers to common questions and guide on using ASCOT RecordsHub</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="helpSearch" placeholder="Search help topics..." onkeyup="filterHelp()">
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- HELP CARDS -->
                <div class="help-grid" id="helpCardsContainer">
                    <!-- 1. Submitting Documents Box -->
                    <div class="help-card" onclick="openModal('modalSubmit')">
                        <div class="help-card-icon"><i data-lucide="file-plus"></i></div>
                        <h3>Submitting Documents</h3>
                        <p>Learn how to properly upload PDF files, add control numbers, and route documents to the target department.</p>
                    </div>

                    <!-- 2. Tracking & Monitoring Box -->
                    <div class="help-card" onclick="location.href='tracking.php'">
                        <div class="help-card-icon"><i data-lucide="search"></i></div>
                        <h3>Tracking & Monitoring</h3>
                        <p>Track real-time status of submitted requests using your unique Control ID from the Tracking page.</p>
                    </div>

                    <!-- 3. Contact Support Box -->
                    <div class="help-card" onclick="openModal('modalContact')">
                        <div class="help-card-icon"><i data-lucide="mail"></i></div>
                        <h3>Contact Support</h3>
                        <p>Need urgent technical assistance? Reach out directly to the ASCOT IT Services & Records Office team.</p>
                    </div>
                </div>

                <!-- FREQUENTLY ASKED QUESTIONS -->
                <div class="card">
                    <div class="card-title" style="margin-bottom: 16px;">Frequently Asked Questions (FAQs)</div>

                    <div id="faqList">
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <i data-lucide="help-circle" style="width: 16px; color: #064e3b;"></i> How long does document approval usually take?
                            </div>
                            <div class="faq-answer">Standard requests take around 1 to 3 working days depending on the approving authority and department queue.</div>
                        </div>

                        <div class="faq-item" style="margin-top: 14px;">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <i data-lucide="help-circle" style="width: 16px; color: #064e3b;"></i> What file formats are supported for uploads?
                            </div>
                            <div class="faq-answer">We strongly recommend uploading documents in <strong>PDF format</strong> up to 10MB to maintain document formatting and official integrity.</div>
                        </div>

                        <div class="faq-item" style="margin-top: 14px;">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <i data-lucide="help-circle" style="width: 16px; color: #064e3b;"></i> Who can I contact for wrong routing or document cancellation?
                            </div>
                            <div class="faq-answer">You may send a direct message via the <strong>Messages</strong> tab or visit the ASCOT Records Management Office at the Main Campus.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL POPUP FOR SUBMITTING DOCUMENTS -->
    <div class="help-modal" id="modalSubmit">
        <div class="help-modal-content">
            <div class="help-modal-close" onclick="closeModal('modalSubmit')"><i data-lucide="x"></i></div>
            <h3 style="color: #064e3b; margin-bottom: 10px; font-size: 16px;">Guide: Submitting Documents</h3>
            <p style="font-size: 12.5px; color: #334155; line-height: 1.6; margin-bottom: 12px;">
                1. Navigate to the <strong>Submit Document</strong> section.<br>
                2. Upload your file in <strong>PDF format</strong> (maximum of 10MB only).<br>
                3. Fill out the required information and select the appropriate department routing.<br>
                4. Click submit to generate and receive your unique Control ID.
            </p>
            <button class="primary-btn" onclick="closeModal('modalSubmit')" style="width: 100%; justify-content: center;">Got it</button>
        </div>
    </div>

    <!-- MODAL POPUP FOR CONTACT SUPPORT -->
    <div class="help-modal" id="modalContact">
        <div class="help-modal-content">
            <div class="help-modal-close" onclick="closeModal('modalContact')"><i data-lucide="x"></i></div>
            <h3 style="color: #064e3b; margin-bottom: 10px; font-size: 16px;">ASCOT IT Services & Records Support</h3>
            <p style="font-size: 12.5px; color: #334155; line-height: 1.6; margin-bottom: 12px;">
                For urgent inquiries or technical concerns:<br><br>
                📧 <strong>Email:</strong> support@ascot.edu.ph<br>
                📞 <strong>Office Hours:</strong> Monday to Friday (8:00 AM - 5:00 PM)<br>
                📍 <strong>Location:</strong> ASCOT Main Campus, Records Management Office
            </p>
            <button class="primary-btn" onclick="closeModal('modalContact')" style="width: 100%; justify-content: center;">Close</button>
        </div>
    </div>

    <!-- External Sidebar Script & Icon Initialization -->
    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();

        // FAQ Accordion Toggle
        function toggleFaq(element) {
            const parent = element.parentElement;
            parent.classList.toggle('active');
        }

        // Search Filter for FAQs
        function filterHelp() {
            let input = document.getElementById('helpSearch').value.toLowerCase();
            let faqs = document.querySelectorAll('.faq-item');

            faqs.forEach(faq => {
                let text = faq.innerText.toLowerCase();
                if (text.includes(input)) {
                    faq.style.display = "";
                } else {
                    faq.style.display = "none";
                }
            });
        }

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            lucide.createIcons();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
    </script>
</body>
</html>