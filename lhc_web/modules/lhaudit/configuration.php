<?php

$tpl = erLhcoreClassTemplate::getInstance('lhaudit/configuration.tpl.php');

if (!class_exists('erLhcoreClassInstance') && $_SERVER['REQUEST_METHOD'] === 'POST' && $Params['user_parameters_unordered']['action'] == 'kill' && is_numeric($Params['user_parameters_unordered']['id'])) {
    if (!isset($Params['user_parameters_unordered']['csfr']) || !$currentUser->validateCSFRToken($Params['user_parameters_unordered']['csfr'])) {
        die('Invalid CSRF Token');
        exit;
    }
    $db = ezcDbInstance::get();
    $stmt = $db->prepare('KILL :process_id');
    $stmt->bindValue(':process_id', (int)$Params['user_parameters_unordered']['id'],PDO::PARAM_INT);
    $stmt->execute();
    exit;
}

$auditOptions = erLhcoreClassModelChatConfig::fetch('audit_configuration');
$data = (array)$auditOptions->data;

if ( isset($_POST['ReloadOperatorsBackOffice']) ) {
    erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.reload_backoffice',array());
}

if ( isset($_POST['StoreOptions']) ) {

    if (!isset($_POST['csfr_token']) || !$currentUser->validateCSFRToken($_POST['csfr_token'])) {
        erLhcoreClassModule::redirect();
        exit;
    }
    
    $definition = array(
        'days_log' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'int'),
        'log_js' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
        'log_user' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
        'log_block' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
        'log_files' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
        'log_routing' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
        'log_objects' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw',null,FILTER_REQUIRE_ARRAY),
    );

    $form = new ezcInputForm( INPUT_POST, $definition );
    $Errors = array();

    if ( $form->hasValidData( 'days_log' )) {
        $data['days_log'] = $form->days_log ;
    } else {
        $data['days_log'] = 90;
    }

    if ( $form->hasValidData( 'log_objects' )) {
        $data['log_objects'] = $form->log_objects ;
    } else {
        $data['log_objects'] = array();
    }

    if ( $form->hasValidData( 'log_js' )) {
        $data['log_js'] = 1;
    } else {
        $data['log_js'] = 0;
    }
    
    if ( $form->hasValidData( 'log_block' )) {
        $data['log_block'] = 1;
    } else {
        $data['log_block'] = 0;
    }

    if ( $form->hasValidData( 'log_routing' )) {
        $data['log_routing'] = 1;
    } else {
        $data['log_routing'] = 0;
    }
    
    if ( $form->hasValidData( 'log_files' )) {
        $data['log_files'] = 1;
    } else {
        $data['log_files'] = 0;
    }

    if ( $form->hasValidData( 'log_user' )) {
        $data['log_user'] = 1;
    } else {
        $data['log_user'] = 0;
    }

    $auditOptions->explain = '';
    $auditOptions->type = 0;
    $auditOptions->hidden = 1;
    $auditOptions->identifier = 'audit_configuration';
    $auditOptions->value = serialize($data);
    $auditOptions->saveThis();

    $tpl->set('updated','done');
}

$tpl->set('audit_options',$data);

$Result['content'] = $tpl->fetch();

$Result['path'] = array(
    array('url' => erLhcoreClassDesign::baseurl('system/configuration'),'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('department/edit','System configuration')),
    array(
        'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('audit/options', 'Options')
    )
);

?>