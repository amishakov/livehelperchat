<?php
#[\AllowDynamicProperties]
class erLhcoreClassModelCannedMsgTagLink
{
    use erLhcoreClassDBTrait;

    public static $dbTable = 'lh_canned_msg_tag_link';

    public static $dbTableId = 'id';

    public static $dbSessionHandler = 'erLhcoreClassChat::getSession';

    public static $dbSortOrder = 'DESC';

    public function getState()
    {
        return array(
            'id' => $this->id,
            'tag_id' => $this->tag_id,
            'canned_id' => $this->canned_id,
        );
    }

    public function __get($var)
    {
        switch ($var) {

            default:
                break;
        }
    }

    public static function similar_text_match($keywords, $partial_word, $top = 1, $min_percent = 0) {
        $matches = [];

        foreach ($keywords as $word) {
            similar_text($partial_word, $word->tag, $percent);
            if ($percent >= $min_percent) {
                $matches[] = ['id' => $word->id, 'percent' => $percent];
            }
        }

        usort($matches, function ($a, $b) {
            return $b['percent'] <=> $a['percent'];
        });

        return array_slice(array_column($matches, 'id'), 0, $top);
    }


    public static function getTagByKeyword($keyword, $paramsExecution)
    {
        $filterTagLinks = array('limit' => false);

        $settingsCannedSuggester = erLhcoreClassModelChatConfig::fetch('canned_suggester_settings')->data;

        $keywordPartial = mb_substr($keyword,0, $settingsCannedSuggester['first_n_letters'] ?? 1);

        if (!empty($keyword)) {
            $filterTagLinks['innerjoin']['lh_canned_msg_tag_link'] = ['`lh_canned_msg_tag`.`id`','`lh_canned_msg_tag_link`.`tag_id`'];
            $filterTagLinks['filterlikeright']['`lh_canned_msg_tag`.`tag`'] = $keywordPartial;
        }

        // Apply tag linking query
        $filterTagLinks['innerjoin']['lh_canned_msg'] = ['`lh_canned_msg`.`id`','`lh_canned_msg_tag_link`.`canned_id`'];

        // Apply filtering query
        $filterTagLinks['customfilter'][] = '( 
                                    (department_id = 0 AND user_id = 0) OR 
                                    (department_id = ' . (int)$paramsExecution['chat']->dep_id . ' AND user_id = 0) OR
                                    (user_id = ' . (int)$paramsExecution['user']->id . ' AND department_id = 0) OR
                                    (`lh_canned_msg`.`id` IN (SELECT canned_id FROM lh_canned_msg_dep WHERE dep_id = ' . (int)$paramsExecution['chat']->dep_id . ') AND (user_id = 0 OR ' . (int)$paramsExecution['user']->id . '))
        )';

        $tagsRelated = erLhcoreClassModelCannedMsgTag::getList($filterTagLinks);

        if (empty($tagsRelated)) {
            return [];
        }

        $similarTags = self::similar_text_match($tagsRelated, $keyword, ($settingsCannedSuggester['top_n_match'] ?? 1), ($settingsCannedSuggester['min_percentage'] ?? 0));

        if (empty($similarTags)) {
            return [];
        }

        $paramsExecution['keyword_alias'] = $tagsRelated[$similarTags[0]]->tag;
        $paramsExecution['ignore_keyword'] = true;
        $paramsExecution['tag_link_id'] = $similarTags;

        return self::formatSuggester('', $paramsExecution);
    }

    public static function formatSuggester($keyword, $paramsExecution)
    {
        $filterTagLinks = array('limit' => false);

        if (!empty($keyword)) {
            $filterTagLinks['innerjoin']['lh_canned_msg_tag'] = ['`lh_canned_msg_tag`.`id`','`lh_canned_msg_tag_link`.`tag_id`'];
            $filterTagLinks['filterlikeright']['`lh_canned_msg_tag`.`tag`'] = $keyword;
        }

        if (isset($paramsExecution['tag_link_id'])) {
            $filterTagLinks['filterin']['`lh_canned_msg_tag_link`.`tag_id`'] = $paramsExecution['tag_link_id'];
        }

        // Apply tag linking query
        $filterTagLinks['innerjoin']['lh_canned_msg'] = ['`lh_canned_msg`.`id`','`lh_canned_msg_tag_link`.`canned_id`'];

        // Apply filtering query
        $filterTagLinks['customfilter'][] = '( 
                                    (department_id = 0 AND user_id = 0) OR 
                                    (department_id = ' . (int)$paramsExecution['chat']->dep_id . ' AND user_id = 0) OR
                                    (user_id = ' . (int)$paramsExecution['user']->id . ' AND department_id = 0) OR
                                    (`lh_canned_msg`.`id` IN (SELECT canned_id FROM lh_canned_msg_dep WHERE dep_id = ' . (int)$paramsExecution['chat']->dep_id . ') AND (user_id = 0 OR ' . (int)$paramsExecution['user']->id . '))
        )';

        $tagLinks = self::getList($filterTagLinks);

        if (empty($tagLinks)) {
            if (empty($keyword)) {
                return array();
            } elseif (!isset($paramsExecution['ignore_keyword'])) {
                return self::getTagByKeyword($keyword, $paramsExecution);
            }
            return array();
        }

        $tagsId = array();

        $cannedMessagesIds = array();
        foreach ($tagLinks as $tagLink) {
            $tagsId[] = $tagLink->tag_id;
            $cannedMessagesIds[] = $tagLink->canned_id;
        }

        $tags = erLhcoreClassModelCannedMsgTag::getList(array('filterin' => array('id' => $tagsId)));

        $cannedMessagesAll = erLhcoreClassModelCannedMsg::getCannedMessages($paramsExecution['chat']->dep_id, $paramsExecution['user']->id, array('id' => $cannedMessagesIds));
        $shrinkedList = false;

        if (count($cannedMessagesAll) > 150) {
            // Preserve keys
            $cannedMessageShirked = [];

            $counter = 0;
            foreach ($cannedMessagesAll as $cannedMessage) {
                if ($counter >= 150) {
                    break;
                }
                $cannedMessageShirked[$cannedMessage->id] = $cannedMessage;
                $counter++;
            }

            $cannedMessagesAll = $cannedMessageShirked;
            $shrinkedList = true;
        }

        $chat = $paramsExecution['chat'];
        $user = $paramsExecution['user'];

        $replaceArray = array(
            '{nick}' => $chat->nick,
            '{email}' => $chat->email,
            '{phone}' => $chat->phone,
            '{operator}' => $user->name_support
        );

        $additionalData = $chat->additional_data_array;

        if (is_array($additionalData)) {
            foreach ($additionalData as $row) {
                if (isset($row['identifier']) && $row['identifier'] != '') {
                    $replaceArray['{' . $row['identifier'] . '}'] = $row['value'];
                }
            }
        }

        \LiveHelperChat\Models\Departments\UserDepAlias::getAlias(array('scope' => 'canned_replace', 'replace_array' => & $replaceArray, 'chat' => $chat, 'user' => $user));
        erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.workflow.canned_message_replace', array(
            'chat' => $chat,
            'replace_array' => & $replaceArray,
            'user' => $user,
            'items' => & $cannedMessagesAll
        ));

        $replaceCustomArgs = [];
        foreach ($cannedMessagesAll as $item) {
            // Set proper message by language
            $item->setMessageByChatLocale($chat->chat_locale);

            foreach (['msg','fallback_msg'] as $metaMsg) {
                $matchesMessage = [];
                preg_match_all('/\{[A-Za-z0-9\_]+\}/is',$item->{$metaMsg}, $matchesMessage);
                if (isset($matchesMessage[0]) && !empty($matchesMessage[0])) {
                    foreach ($matchesMessage[0] as $replaceItem) {
                        if (key_exists($replaceItem,$replaceArray) == false) {
                            $replaceCustomArgs[] = $replaceItem;
                        }
                    }
                }
            }
        }

        $replaceCustomArgs = array_unique($replaceCustomArgs);

        if (!empty($replaceCustomArgs)) {

            $identifiers = [];
            $identifiersApplied = [];

            foreach ($replaceCustomArgs as $replaceArg) {
                $identifiers[] = str_replace(['{','}'],'', $replaceArg);
            }

            $replaceRules = erLhcoreClassModelCannedMsgReplace::getList(array(
                'sort' => 'repetitiveness DESC', // Default translation will be the last one if more than one same identifier is found
                'limit' => false,
                'filterin' => array('identifier' => $identifiers)));

            foreach ($replaceRules as $replaceRule) {
                if ($replaceRule->is_active && !in_array($replaceRule->identifier,$identifiersApplied)) {
                    $replaceArray['{' . $replaceRule->identifier . '}'] = $replaceRule->getValueReplace(['chat' => $chat, 'user' => $user]);
                    $identifiersApplied[] = $replaceRule->identifier;
                }
            }
        }

        foreach ($cannedMessagesAll as $item) {
            $item->setReplaceData($replaceArray);
        }

        $returnArray = array();

        $index = 0;
        foreach ($cannedMessagesAll as & $cannedMessage) {
            $cannedMessage->priority_index = $index;
            $index++;
        }

        $tagLinkGroups = array();
        foreach ($tagLinks as $tagLink) {
            if (isset($cannedMessagesAll[$tagLink->canned_id])) {
                $tagLinkGroups[$tagLink->tag_id][$cannedMessagesAll[$tagLink->canned_id]->priority_index] = $cannedMessagesAll[$tagLink->canned_id];
            }
        }

        foreach ($tagLinkGroups as $tagId => $cannedMessages) {
            ksort($cannedMessages); // Sort by canned message priority and title
            $tag =  $tags[$tagId];
            $tag->cnt = count($cannedMessages);
            $returnArray[$tags[$tagId]->tag] = array(
                'tag' => $tags[$tagId],
                'messages' => $cannedMessages,
                'shrinked' => $shrinkedList
            );
        }

        // Sort by tag title
        ksort($returnArray);

        return $returnArray;
    }

    private $replaceData = array();

    public $id = null;

    public $tag_id = 0;
    public $canned_id = 0;
}

?>