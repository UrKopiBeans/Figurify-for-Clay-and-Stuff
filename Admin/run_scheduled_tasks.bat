@echo off
REM ==============================================================
REM  Clay and Stuff — scheduled tasks (Windows / XAMPP)
REM  Paano i-setup (isang beses lang):
REM   1. Buksan ang "Task Scheduler" sa Windows
REM   2. Create Basic Task -> pangalan: "Clay and Stuff tasks"
REM   3. Trigger: Daily, tapos sa Properties > Triggers > Edit:
REM      "Repeat task every: 1 hour" for a duration of "Indefinitely"
REM   4. Action: Start a program -> piliin ang file na ito (.bat)
REM  Palitan ang mga path sa ibaba kung iba ang lokasyon ng XAMPP/project.
REM ==============================================================

set PHP_EXE=C:\xampp\php\php.exe
set PROJECT=C:\xampp\htdocs\clayandstuff

"%PHP_EXE%" "%PROJECT%\Admin\run_scheduled_tasks.php" >> "%PROJECT%\Admin\scheduled_tasks.log" 2>&1
