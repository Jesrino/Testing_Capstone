<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/Appointments.php";

$clientId = $_SESSION['user_id'];
$cursor = listClientAppointments($clientId);
?>
<h2>Your appointments</h2>
<ul>
  <?php foreach ($cursor as $appt): ?>
    <li>
      <?php echo $appt['date'] . ' ' . $appt['time'] . ' — ' . $appt['status']; ?>
    </li>
  <?php endforeach; ?>
</ul>
<?php include("../includes/footer.php"); ?>
