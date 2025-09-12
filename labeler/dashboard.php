<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/enhanced_functions.php';

// Check if user is labeler
if ($_SESSION['role'] !== 'labeler') {
    header('Location: ../index.php');
    exit;
}

$functions = new Functions();
$ef = new EnhancedFunctions();
$userId = $_SESSION['user_id'];

// Get labeler's tasks and statistics
$myTasks = $functions->getLabelerTasks($userId);
$completedTasks = $functions->getCompletedTasks($userId);

// Get available tasks (both single and multi-document)
$availableSingleTasks = $functions->getAvailableTasks('single');
$availableMultiTasks = $ef->getAllDocumentGroups('pending', 10, 0);

// Calculate statistics
$totalTasks = count($myTasks['data'] ?? []);
$completedCount = count(array_filter($myTasks['data'] ?? [], function($task) {
    return $task['status'] === 'completed';
}));
$inProgressCount = $totalTasks - $completedCount;

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="labeling.php">
                            <i class="fas fa-tag me-2"></i>Gán nhãn đơn
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="multi_labeling.php">
                            <i class="fas fa-tags me-2"></i>Gán nhãn đa văn bản
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_tasks.php">
                            <i class="fas fa-tasks me-2"></i>Nhiệm vụ của tôi
                        