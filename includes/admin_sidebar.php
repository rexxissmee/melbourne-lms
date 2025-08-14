<?php
$current = basename($_SERVER['PHP_SELF']);
$isActive = function (array $files) use ($current) {
    return in_array($current, $files, true) ? ' active' : '';
};
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['dashboard.php']); ?>" href="../admin/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>User Management</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['users.php']); ?>" href="../admin/users.php">
                    <i class="fas fa-users"></i> All Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['students.php']); ?>" href="../admin/students.php">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['instructors.php']); ?>" href="../admin/instructors.php">
                    <i class="fas fa-chalkboard-teacher"></i> Instructors
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Course Management</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['courses.php', 'course_view.php']); ?>" href="../admin/courses.php">
                    <i class="fas fa-book"></i> All Courses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['enrollments.php']); ?>" href="../admin/enrollments.php">
                    <i class="fas fa-user-plus"></i> Enrollments
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['analytics.php', 'reports.php']); ?>" href="../admin/analytics.php">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['reports.php']); ?>" href="../admin/reports.php">
                    <i class="fas fa-file-alt"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['settings.php', 'system_logs.php']); ?>" href="../admin/settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?php echo $isActive(['system_logs.php']); ?>" href="../admin/system_logs.php">
                    <i class="fas fa-list"></i> System Logs
                </a>
            </li>
        </ul>
    </div>
</nav>