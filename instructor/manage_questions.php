<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('instructor');
requireCsrf();
$quizId=(int)($_GET['quiz_id']??0); $error=''; $success='';
$st=$pdo->prepare("SELECT q.*,c.course_name FROM quizzes q JOIN courses c ON c.course_id=q.course_id WHERE q.quiz_id=? AND q.created_by_instructor_id=?");$st->execute([$quizId,$_SESSION['user_id']]);$quiz=$st->fetch();
if(!$quiz) die('Quiz not found or access denied.');
if($_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';
 try{
  if($action==='add_question'){
   $st=$pdo->prepare("INSERT INTO questions (quiz_id,question_text,option_a,option_b,option_c,option_d,correct_answer) VALUES (?,?,?,?,?,?,?)");
   $st->execute([$quizId,trim($_POST['question_text']),trim($_POST['option_a']),trim($_POST['option_b']),trim($_POST['option_c']),trim($_POST['option_d']),$_POST['correct_answer']]);
   $success='Question added successfully.';
  } elseif($action==='delete_question'){
   $st=$pdo->prepare("DELETE FROM questions WHERE question_id=? AND quiz_id=?");$st->execute([(int)$_POST['question_id'],$quizId]);$success='Question deleted.';
  }
 }catch(PDOException $e){$error='Could not save the question.';}
}
$st=$pdo->prepare('SELECT * FROM questions WHERE quiz_id=? ORDER BY question_id');$st->execute([$quizId]);$questions=$st->fetchAll();
$pageTitle='Manage Questions'; require __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">QUIZ BUILDER</div><h1><?=e($quiz['title'])?></h1><p><?=e($quiz['course_name'])?></p></div></div>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<section class="dashboard-card"><div class="card-header"><div><h2>Add Question</h2><p class="text-muted">Add four choices and select the correct answer.</p></div></div>
<form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="add_question"><div class="input-group"><label>Question</label><textarea name="question_text" required></textarea></div><div class="form-grid"><div class="input-group"><label>Option A</label><input name="option_a" required></div><div class="input-group"><label>Option B</label><input name="option_b" required></div><div class="input-group"><label>Option C</label><input name="option_c" required></div><div class="input-group"><label>Option D</label><input name="option_d" required></div></div><div class="input-group"><label>Correct Answer</label><select name="correct_answer" required><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div><button class="btn btn-primary">Add Question</button></form></section>
<section class="dashboard-card"><div class="card-header"><h2>Questions (<?=count($questions)?>)</h2></div><?php if(!$questions):?><div class="empty-state">No questions added yet.</div><?php else:?><div class="table-container"><table><thead><tr><th>#</th><th>Question</th><th>Correct</th><th>Action</th></tr></thead><tbody><?php foreach($questions as $i=>$q):?><tr><td><?=$i+1?></td><td><?=e($q['question_text'])?></td><td><?=e($q['correct_answer'])?></td><td><form method="POST" onsubmit="return confirm('Delete this question?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="<?=$q['question_id']?>"><button class="btn btn-danger btn-small">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
