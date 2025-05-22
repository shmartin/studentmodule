<?php
session_start();

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_destroy();
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['uid'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviewer Chair Module</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background-color: #f8f9fa;
    }
    .btn-main {
      background-color: #3CB371;
      color: white;
    }
    .btn-main:hover {
      background-color: #349966;
      color: white;
    }
    .modal-header {
      background-color: #3CB371;
      color: white;
    }
    .table thead {
      background-color: #3CB371;
      color: white;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #3CB371;">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="bi bi-people-fill"></i> Reviewer Chair Module</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" href="#"><i class="bi bi-person-badge-fill"></i> Hello <?php echo $_SESSION['name'];?>!</a>
        </li>
        <li class="nav-item">
		  <a class="nav-link active" href="add_user.php">
		    <i class="bi bi-person-badge-fill"></i> Add User
		  </a>
		</li>
        <li class="nav-item">
          <a class="nav-link" href="index.php?logout=true"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
      </ul>

    </div>
  </div>
</nav>

  <div class="container mt-5">
    <h2 class="mb-4 text-center text-success"><i class="bi bi-people-fill"></i> Assign Reviewer</h2>
    <div class="mb-2">
      <input type="text" id="liveSearch" class="form-control" placeholder="Search title, author, adviser, or program...">
    </div>
    <div class="card shadow rounded">
      <div class="card-body">
        <table class="table table-bordered table-hover table-striped">
          <thead>
            <tr>
              <th class="text-center"><i class="bi bi-file-earmark-text"></i> Title</th>
              <th class="text-center"><i class="bi bi-person"></i> Author</th>
              <th class="text-center"><i class="bi bi-person-badge"></i> Adviser</th>
              <th class="text-center"><i class="bi bi-journal-text"></i> Program</th>
              <th class="text-center"><i class="bi bi-people"></i> Reviewers</th>
              <th class="text-center"><i class="bi bi-gear"></i> Action</th>

            </tr>
          </thead>
          <tbody id="documentTable">
            <?php
            include 'dbcon.php';
            $query = "SELECT d.did, d.dtitle, u.firstname AS author_firstname, u.lastname AS author_lastname, 
           dd.dadviser, p.description, 
           COALESCE(GROUP_CONCAT(CONCAT(us.firstname, ' ', us.lastname) SEPARATOR ', '), 'No reviewers assigned') AS reviewers
            FROM document d  
            JOIN document_details dd ON d.did = dd.did  
            JOIN users u ON dd.dauthor = u.uid  
            JOIN program p ON dd.program = p.pid  
            LEFT JOIN document_reviewers dr ON d.did = dr.did  
            LEFT JOIN users us ON dr.reviewer_id = us.uid  
            GROUP BY d.did, d.dtitle, author_firstname, author_lastname, dd.dadviser, p.description;";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
              echo "<tr>
                <td>{$row['dtitle']}</td>
                <td>{$row['author_firstname']} {$row['author_lastname']}</td>
                <td>{$row['dadviser']}</td>
                <td>{$row['description']}</td>
                <td>{$row['reviewers']} </td>
                <td><button class='btn btn-main btn-sm' data-bs-toggle='modal' data-bs-target='#assignModal' 
                            data-did='{$row['did']}'>Assign Reviewer</button></td>
              </tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<!--   <div class="modal-header">
          <h5 class="modal-title" id="assignModalLabel">Assign Reviewer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div> -->

  <!-- Assign Modal -->
  <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="assignModalLabel">Assign Reviewer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="assignForm">
            <input type="hidden" name="did" id="docId">
            <div id="reviewerList" class="mb-3">
              <!-- Reviewer checkboxes will be loaded here -->
            </div>
            <div class="mb-3">
              <button type="button" class="btn btn-secondary btn-sm" onclick="showAddReviewer()">Add New Reviewer</button>
            </div>
            <div id="addReviewerForm" style="display:none">
              <div class="row g-2">
                <div class="col-md-4">
                  <input type="text" name="firstname" placeholder="First Name" class="form-control">
                </div>
                <div class="col-md-4">
                  <input type="text" name="lastname" placeholder="Last Name" class="form-control">
                </div>
                <div class="col-md-4">
                  <input type="email" name="email" placeholder="Email" class="form-control">
                </div>
              </div>
              <div class="mt-2">
                <input type="password" name="password" placeholder="Password" class="form-control" value="password" style="display: none;">

                <button type="button" class="btn btn-main mt-2" onclick="addReviewer()">Save Reviewer</button>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-main">Assign Selected Reviewers</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
  <script>
    var assignModal = document.getElementById('assignModal');
    assignModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var did = button.getAttribute('data-did');
      $('#docId').val(did);
      $.get('manage_reviewers.php', { did: did }, function(data) {
        $('#reviewerList').html(data);
      });
    });

    function showAddReviewer() {
      $('#addReviewerForm').slideToggle();
    }


    function addReviewer() {
      var formData = $('#assignForm').serialize();
      formData += '&action=add_reviewer';
      $.post('manage_reviewers.php', formData, function(response) {
        Swal.fire({
          icon: 'success',
          title: 'Reviewer Added',
          text: response,
          confirmButtonText: 'OK'
        }).then(() => {
          
          $('#addReviewerForm').slideUp();

      
          var did = $('#docId').val();
          $.get('manage_reviewers.php', { did: did }, function(data) {
            $('#reviewerList').html(data);
          });
        });
      }).fail(function(xhr) {
        Swal.fire('Error', xhr.responseText, 'error');
      });
    }


    $('#assignForm').submit(function(e) {
      e.preventDefault();
      var formData = $(this).serialize();
      formData += '&action=assign_reviewers';
      $.post('manage_reviewers.php', formData, function(response) {
        Swal.fire('Success', response, 'success').then(() => {
          // Optionally close the modal
          var modal = bootstrap.Modal.getInstance(assignModal);
          modal.hide();
          // Reload the page
          location.reload();
        });
      }).fail(function(xhr) {
        Swal.fire('Error', xhr.responseText, 'error');
      });
    });




    $('#liveSearch').on('input', function() {
      var query = $(this).val();
      $.get('search_documents.php', { query: query }, function(data) {
        $('#documentTable').html(data);
      });
    });
  </script>
</body>
</html>
