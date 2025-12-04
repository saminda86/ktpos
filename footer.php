<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // -----------------------------------------------------------
        // SweetAlert Functions (පොදු SweetAlert ශ්‍රිතයන් - HTML භාවිතයෙන්)
        // -----------------------------------------------------------

        /**
         * සාර්ථක පණිවිඩයක් පෙන්වයි.
         * @param {string} message - පෙන්විය යුතු පණිවිඩය.
         */
        function showSuccessAlert(message) {
            Swal.fire({
                icon: 'success',
                title: 'සාර්ථකයි!', 
                html: '<div style="text-align: center; margin-top: 10px; line-height: 1.4;"><strong>' + message + '</strong></div>', 
                confirmButtonText: 'හරි'
            });
        }

        /**
         * දෝෂ පණිවිඩයක් පෙන්වයි.
         * @param {string} message - පෙන්විය යුතු දෝෂ පණිවිඩය.
         */
        function showErrorAlert(message) {
            Swal.fire({
                icon: 'error',
                title: 'දෝෂයක්!', 
                html: '<div style="text-align: center; margin-top: 10px; line-height: 1.4;"><strong>' + message + '</strong></div>', 
                confirmButtonText: 'හරි'
            });
        }

        /**
         * යම් ක්‍රියාවක් සඳහා තහවුරු කිරීමක් ඉල්ලයි.
         */
        function showConfirmationAlert(title, rawJsonText, callback) {
            
            // 🛑 FIX: JSON string එක parse කර, එහි ඇති HTML Tags නිවැරදිව Render කරයි
            const decodedText = JSON.parse(rawJsonText);
            
            // 🛑 FIX: Text Center කිරීම සඳහා <p> tag එකක් තුළට යොදයි
            const final_html = '<p style="text-align: center; line-height: 1.6; margin: 0;">' + decodedText + '</p>';
            
            Swal.fire({
                title: title,
                html: final_html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#27b19d', 
                cancelButtonColor: '#dc3545', 
                confirmButtonText: 'ඔව්, තහවුරු කරන්න', 
                cancelButtonText: 'අවලංගු කරන්න'
            }).then((result) => {
                if (result.isConfirmed) {
                    callback();
                }
            });
        }

        // -----------------------------------------------------------
        // Form Submission (PHP හරහා ලැබෙන පණිවිඩ පෙන්වීම)
        // -----------------------------------------------------------
        // Note: PHP variables from the session must be URL-encoded for security and clean display.

        <?php if (isset($_SESSION['success_message'])): ?>
            // URL-encode කර ඇති පණිවිඩය decode කර පෙන්වයි
            showSuccessAlert(decodeURI('<?php echo rawurlencode($_SESSION['success_message']); unset($_SESSION['success_message']); ?>'));
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            // URL-encode කර ඇති පණිවිඩය decode කර පෙන්වයි
            showErrorAlert(decodeURI('<?php echo rawurlencode($_SESSION['error_message']); unset($_SESSION['error_message']); ?>'));
        <?php endif; ?>
        
    </script>
</body>
</html>