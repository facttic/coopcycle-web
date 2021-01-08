<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210108082409 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Fix tasks unassigned after completion';
    }

    public function up(Schema $schema) : void
    {
        $taskEvents = $this->connection->prepare('SELECT id, name, data, created_at FROM task_event WHERE task_id = :task_id ORDER BY created_at DESC');

        $getUserId = $this->connection->prepare('SELECT id FROM api_user WHERE username = :username');

        $tasks = $this->connection->prepare('SELECT id, done_before FROM task WHERE status = \'DONE\' AND assigned_to IS NULL');
        $tasks->execute();

        while ($task = $tasks->fetch()) {

            $taskEvents->bindParam('task_id', $task['id']);
            $taskEvents->execute();

            $assignedTo = null;
            while ($taskEvent = $taskEvents->fetch()) {
                if ('task:assigned' === $taskEvent['name']) {
                    $data = json_decode($taskEvent['data'], true);
                    $assignedTo = $data['username'];
                    break;
                }
            }

            if ($assignedTo) {
                $getUserId->bindParam('username', $assignedTo);
                $getUserId->execute();
                $user = $getUserId->fetch();

                $this->addSql('UPDATE task SET assigned_to = :user_id WHERE id = :task_id', [
                    'user_id' => $user['id'],
                    'task_id' => $task['id'],
                ]);
                $this->addSql('DELETE FROM task_event WHERE task_id = :task_id AND name = \'task:unassigned\' AND created_at > (SELECT created_at FROM task_event WHERE task_id = :task_id AND name = \'task:done\')', [
                    'task_id' => $task['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
