import React, { PureComponent } from 'react';
import parse, { domToReact } from 'html-react-parser';
import { connect } from "react-redux";
import { withTranslation } from 'react-i18next';
import { parseScript } from "../actions/chatActions";
import { helperFunctions } from "../lib/helperFunctions";

@connect((store) => {
    return {
        chatwidget: store.chatwidget
    };
})

class ChatBotIntroMessage extends PureComponent {

    state = {
        value: ''
    };

    constructor(props) {
        super(props);
        this.abstractClick = this.abstractClick.bind(this);
        this.updateTriggerClicked = this.updateTriggerClicked.bind(this);
        this.processBotAction = this.processBotAction.bind(this);
        this.disableEditor = false;
    }

    getDirectInnerText(element) {
        var childNodes = element.childNodes;
        var result = '';

        for (var i = 0; i < childNodes.length; i++) {
            if(childNodes[i].nodeType == 3) {
                result += childNodes[i].data.trim();
            }
        }

        return result;
    }

    addLoader(attrs, element) {

        if (this.props.printButton == true && !attrs["data-no-msg"] && (attrs.type == 'button' || element.tagName === 'A')) {
            this.setState({value : this.getDirectInnerText(element)});
            if (element.tagName !== 'A') {
                this.removeMetaMessage(attrs['data-id']);
            }
        }

        if (attrs["data-no-msg"] && !attrs["data-no-change"] && attrs.type == 'button') {
            element.setAttribute("disabled","disabled");
            element.innerHTML = "<i class=\"material-icons\">&#xf113;</i>" + element.innerHTML;
        }
    }

    removeMetaMessage(messageId) {
        var msgArea = document.getElementById('messages-scroll');
        if (msgArea) {
            var x = msgArea.getElementsByClassName("meta-message-" + messageId);
            var i;
            for (i = 0; i < x.length; i++) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }

    /**
     * Here we handle bot buttons actions
     * */
    abstractClick(attrs, e) {

        const { t } = this.props;

        this.addLoader(attrs,e.target);

        if (attrs.onclick.indexOf('lhinst.updateTriggerClicked') !== -1) {
            this.updateTriggerClicked({type:'triggerclicked'}, attrs, e.target);
        } else if (attrs.onclick.indexOf('notificationsLHC.sendNotification') !== -1) {
            // todo
        } else if (attrs.onclick.indexOf('lhinst.buttonClicked') !== -1) {
            this.updateTriggerClicked({type:''}, attrs, e.target);
        } else if (attrs.onclick.indexOf('lhinst.updateChatClicked') !== -1) {
            this.updateTriggerClicked({type:'',mainType: 'updatebuttonclicked'}, attrs, e.target);
        } else if (attrs.onclick.indexOf('lhinst.executeJS') !== -1) {
            parseScript(attrs, this);
        } else if (attrs.onclick.indexOf('lhinst.editGenericStep') !== -1) {
            this.updateTriggerClicked({type:'editgenericstep'}, attrs, e.target);
        } else if (attrs.onclick.indexOf('lhinst.dropdownClicked') !== -1) {
            const list = document.getElementById('id_generic_list-' + attrs['data-id']);
            if (list && list.value != "0" && list.value != "") {
                attrs['data-payload'] = list.value;
                this.updateTriggerClicked({type:'valueclicked'}, attrs, e.target);
            } else {
                alert(t('bot.please_choose'));
            }
        } else {
            helperFunctions.emitEvent('MessageClick',[attrs, this.props.dispatch]);
            console.log('Unknown click event: ' + attrs.onclick);
        }

        e.preventDefault();
    }

    updateTriggerClicked(paramsType, attrs, target) {
        this.props.setBotPayload({type: paramsType['type'], payload: attrs['data-payload'], id : attrs['data-id'], processed : (typeof attrs['data-keep'] === 'undefined')})
    }

    processBotAction(domNode) {

        const attr = domNode.attribs;

        if (attr['data-bot-action'] == 'lhinst.disableVisitorEditor') {
            this.disableEditor = true;
            if (this.props.setTextAreaHidden) {
                this.props.setTextAreaHidden();
            }
        } else if (attr['data-bot-action'] == 'lhinst.setDelay') {
            //this.delayData.push(JSON.parse(attr['data-bot-args']));
        } else if (attr['data-bot-action'] == 'execute-js') {
            eval(domNode.children[0]['data']);
        }
    }

    render() {

        let content = parse(this.props.content, {
            replace: domNode => {
                if (domNode.attribs) {

                    var cloneAttr = Object.assign({}, domNode.attribs);

                    if (domNode.attribs.onclick) {
                        delete domNode.attribs.onclick;
                    }

                    if (domNode.attribs.class) {
                        domNode.attribs.className = domNode.attribs.class;

                        if (domNode.attribs.className.indexOf('message-row') !== -1) {
                            domNode.attribs.className += ' index-row-0';
                        }

                        delete domNode.attribs.class;
                    }

                    if (domNode.name && domNode.name === 'button') {
                        if (cloneAttr.onclick) {
                            return <button {...domNode.attribs} onClick={(e) => this.abstractClick(cloneAttr, e)} >{domToReact(domNode.children)}</button>
                        }
                    } else if (domNode.name && domNode.name === 'a') {

                        if (cloneAttr.onclick) {
                            return <a {...domNode.attribs} onClick={(e) => this.abstractClick(cloneAttr, e)} >{domToReact(domNode.children)}</a>
                        }
                        /**
                         * We can switch target to _top
                         * - if we are in widget mode
                         * - target is _blank
                         * - website where widget is under the same domain
                         * */
                        if (this.props.embedMode && this.props.embedMode == 'widget' && this.props.targetSame && cloneAttr.target && cloneAttr.target == '_blank' && domNode.attribs.href) {
                            const href = domNode.attribs.href;
                            const parentHost = window.parent.location.host;
                            const isSameHost = href.startsWith(`http://${parentHost}`) || href.startsWith(`https://${parentHost}`);
                            if (isSameHost) {
                                domNode.attribs.target = '_top';
                                if (this.props.isMobile) {
                                    return <a {...domNode.attribs} onClick={(e) => this.props.minimizeWidget()}>{domToReact(domNode.children)}</a>
                                } else {
                                    return <a {...domNode.attribs}>{domToReact(domNode.children)}</a>
                                }
                            }
                        }

                    } else if (domNode.name && domNode.name === 'script' && domNode.attribs['data-bot-action']) {
                        this.processBotAction(domNode);
                    }
                }
            }
        });

        return <React.Fragment>{content}{this.state.value != '' && <div data-op-id="0" className="message-row response msg-to-store index-row-0"><div className="msg-body">{this.state.value.split('\n').map((item, idx) => {return (<React.Fragment key={idx}>{item}<br /></React.Fragment>)})}</div></div>}</React.Fragment>

    }
}

export default ChatBotIntroMessage;