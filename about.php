<!DOCTYPE html> 
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About the Developers</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .dev-img {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 50%;
      border: 4px solid #0d6efd; /* กรอบสีน้ำเงิน Bootstrap */
      box-shadow: 0px 4px 10px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body class="bg-light">

  <!-- Header โลโก้ -->
  <div class="container text-center mt-4">
    <img src="rmutsv-logo.png" alt="University Logo" width="120" class="mb-3">
    <h2 class="fw-bold">โครงการพัฒนาระบบจัดการพลังงานด้วยฐานข้อมูล</h2>
    <h5 class="text-muted">คณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคล</h5>
    <hr class="my-4">
  </div>

  <!-- ส่วนผู้พัฒนา -->
  <div class="container">
    <h3 class="text-center mb-4">ผู้พัฒนาระบบ</h3>
    <div class="row justify-content-center">

      <!-- คนที่ 1 -->
      <div class="col-md-4">
        <div class="card shadow text-center p-3 mb-4">
          <img src="dev1.jpg" class="dev-img mx-auto mb-3" alt="Developer 1">
          <h5>ศุภวิชย์ ปานผ่อง</h5>
          <p class="text-muted">Backend & Database</p>
          <p><strong>สาขา:</strong> วิศวกรรมโทรคมนาคม</p>
          <p><strong>Email:</strong> supawish.p@rmutsvmail.com</p>
        </div>
      </div>

      <!-- คนที่ 2 -->
      <div class="col-md-4">
        <div class="card shadow text-center p-3 mb-4">
          <img src="ock.jpg" class="dev-img mx-auto mb-3" alt="Developer 2">
          <h5>ธนาพัทธ์ แก้วแท้</h5>
          <p class="text-muted">Frontend & UI/UX</p>
          <p><strong>สาขา:</strong> วิศวกรรมโทรคมนาคม</p>
          <p><strong>Email:</strong> thanapat.k@rmutsvmail.com</p>
        </div>
      </div>

      <!-- คนที่ 3 -->
      <div class="col-md-4">
        <div class="card shadow text-center p-3 mb-4">
          <img src="monkey.jpg" class="dev-img mx-auto mb-3" alt="Developer 3">
          <h5>เขมทัต คงชู</h5>
          <p class="text-muted">IoT & Hardware</p>
          <p><strong>สาขา:</strong> วิศวกรรมโทรคมนาคม</p>
          <p><strong>Email:</strong> keamatuch.k@rmutsvmail.com</p>
        </div>
      </div>

    </div>

    <!-- ปุ่มย้อนกลับ -->
    <div class="text-center mt-4">
      <a href="index.php" class="btn btn-primary btn-lg">
        ⬅ กลับไปหน้าแรก
      </a>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white text-center py-3 mt-5">
    <p class="mb-0">
      © 2025 โครงการพัฒนาระบบควบคุมอุปกรณ์ IoT <br>
      คณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลศรีวิชัยสงขลา
    </p>
  </footer>

</body>
</html>
