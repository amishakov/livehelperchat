<?php
/**
 * php cron.php -s site_admin -c cron/workflow
 *
 * Run every 10 minits or so. On this cron depends automatic chat transfer and unaswered chats callback.
 *
 * */
echo "Starting chat/workflow at ".date('Y-m-d H:i:s')."\n";

erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.workflow.started',array());

// Unanswered chats callback
echo erLhcoreClassChatWorkflow::mainUnansweredChatWorkflow();

echo "Closed chats - ",erLhcoreClassChatWorkflow::automaticChatClosing(),"\n";

echo "Purged chats - ",erLhcoreClassChatWorkflow::automaticChatPurge(),"\n";

$db = ezcDbInstance::get();

$assignWorkflowTimeout = erLhcoreClassModelChatConfig::fetch('assign_workflow_timeout')->current_value;

echo "Auto assignment starts at ".date('Y-m-d H:i:s')."\n";

if ($assignWorkflowTimeout > 0) {
    foreach (erLhcoreClassChat::getList(array('sort' => 'priority DESC, id ASC', 'limit' => 500, 'filterlt' => array('time' => (time() - $assignWorkflowTimeout)),'filter' => array('status' => erLhcoreClassModelChat::STATUS_PENDING_CHAT))) as $chat) {
        try {
            $db->beginTransaction();
            $chat = erLhcoreClassModelChat::fetchAndLock($chat->id);
            $assignOutput = true;
            if (is_object($chat) && $chat->status == erLhcoreClassModelChat::STATUS_PENDING_CHAT) {
                $assignOutput = erLhcoreClassChatWorkflow::autoAssign($chat, $chat->department, array('cron_init' => true, 'auto_assign_timeout' => true));
                erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.pending_process_workflow',array('chat' => & $chat));
                if ($assignOutput === true) {
                    echo "[".$chat->id."] processed and was auto assigned ".date('Y-m-d H:i:s') . " " . erLhcoreClassChatWorkflow::$lastSuccess . "\n";
                }
            }
            $db->commit();
            if ($assignOutput !== true) {
                echo "[".$chat->id."] processed, but was not auto assigned ".date('Y-m-d H:i:s') . " " . erLhcoreClassChatWorkflow::$lastError . "\n";
            }
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    foreach (erLhcoreClassModelMailconvConversation::getList(array('sort' => 'priority DESC, id ASC', 'limit' => 500, 'customfilter' => ['(`dep_id` IN (SELECT `id` FROM `lh_departament` WHERE `active_mail_balancing` = 1))'], 'filterlt' => array('pnd_time' => (time() - $assignWorkflowTimeout)),'filter' => array('status' => erLhcoreClassModelMailconvConversation::STATUS_PENDING))) as $chat) {
        try {
            $db->beginTransaction();
            $chat = erLhcoreClassModelMailconvConversation::fetchAndLock($chat->id);
            if (is_object($chat) && $chat->status == erLhcoreClassModelMailconvConversation::STATUS_PENDING) {
                erLhcoreClassChatWorkflow::autoAssignMail($chat, $chat->department, array('cron_init' => true, 'auto_assign_timeout' => true));
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
}

foreach (erLhcoreClassChat::getList(array('sort' => 'priority DESC, id ASC', 'limit' => 500, 'filter' => array('status' => erLhcoreClassModelChat::STATUS_PENDING_CHAT))) as $chat) {
    try {
        $db->beginTransaction();
        $chat = erLhcoreClassModelChat::fetchAndLock($chat->id);
        $assignOutput = true;
        if (is_object($chat) && $chat->status == erLhcoreClassModelChat::STATUS_PENDING_CHAT) {
            $assignOutput = erLhcoreClassChatWorkflow::autoAssign($chat, $chat->department, array('cron_init' => true, 'auto_assign_timeout' => false));
            erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.pending_process_workflow',array('chat' => & $chat));
            if ($assignOutput === true) {
                echo "[".$chat->id."] processed and was auto assigned ".date('Y-m-d H:i:s') . " " . erLhcoreClassChatWorkflow::$lastSuccess . "\n";
            }
        }
        $db->commit();
        if ($assignOutput !== true) {
            echo "[".$chat->id."] processed, but was not auto assigned at ".date('Y-m-d H:i:s') . " " . erLhcoreClassChatWorkflow::$lastError . "\n";
        }
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

foreach (erLhcoreClassModelMailconvConversation::getList(array('sort' => 'priority DESC, id ASC', 'limit' => 500, 'customfilter' => ['(`dep_id` IN (SELECT `id` FROM `lh_departament` WHERE `active_mail_balancing` = 1))'], 'filter' => array('status' => erLhcoreClassModelMailconvConversation::STATUS_PENDING))) as $chat) {
    try {
        $db->beginTransaction();
            $chat = erLhcoreClassModelMailconvConversation::fetchAndLock($chat->id);
            if (is_object($chat) && $chat->status == erLhcoreClassModelMailconvConversation::STATUS_PENDING) {
                erLhcoreClassChatWorkflow::autoAssignMail($chat, $chat->department, array('cron_init' => true, 'auto_assign_timeout' => false));
            }
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

echo "Auto assignment ended at ".date('Y-m-d H:i:s')."\n";

$defaultTimeZone = erLhcoreClassModule::$defaultTimeZone;

// Auto Responder In the Background
foreach (erLhcoreClassChat::getList(array('sort' => 'priority DESC, id ASC', 'limit' => 500, 'filterin' => array('status' => [erLhcoreClassModelChat::STATUS_ACTIVE_CHAT,erLhcoreClassModelChat::STATUS_BOT_CHAT,erLhcoreClassModelChat::STATUS_PENDING_CHAT]))) as $chat) {
    try {

        if ($defaultTimeZone != '') {
            date_default_timezone_set($defaultTimeZone);
        }
        
        $db->beginTransaction();

        $chat = erLhcoreClassModelChat::fetchAndLock($chat->id);

        if ($chat instanceof erLhcoreClassModelChat) {

            erLhcoreClassChat::setTimeZoneByChat($chat);

            if ($chat->auto_responder !== false) {
                $chat->auto_responder->chat = $chat;
                $chat->auto_responder->process();
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

erLhcoreClassModule::$defaultTimeZone = $defaultTimeZone;

if (erLhcoreClassModule::$defaultTimeZone != '') {
    date_default_timezone_set(erLhcoreClassModule::$defaultTimeZone);
}

// Inform visitors about unread messages
erLhcoreClassChatWorkflow::autoInformVisitor(erLhcoreClassModelChatConfig::fetch('inform_unread_message')->current_value);

// Cleanup online visitors
erLhcoreClassChatCleanup::cleanupOnlineUsers(array('cronjob' => true));

// Cleanup online operators sessions
erLhcoreClassChatCleanup::onlineOperatorsCleanup(array('cronjob' => true));

// Cleanup online operators sessions
erLhcoreClassChatCleanup::departmentAvailabilityCleanup(array('cronjob' => true));

// Update footprints table if required
erLhcoreClassChatCleanup::updateFootprintBackground();

// Cleanup Audit table if required
erLhcoreClassChatCleanup::cleanupAuditLog();

// Cleanup expired canned messages
erLhcoreClassChatCleanup::cleanupCannedMessages();

echo "Ended chat/workflow at ".date('Y-m-d H:i:s')."\n";

erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.workflow',array());

?>