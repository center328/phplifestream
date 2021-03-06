<?php
require_once 'Zend/Controller/Action.php';

/**
 * Auth controller
 * 
 * 
 */
class AuthController extends Zend_Controller_Action
{
    /**
     * Logout action
     *
     */
    public function logoutAction()
    {
        $auth = Zend_Auth::getInstance();
        $auth->clearIdentity();
    }

    /**
     * Login action
     *
     */
    public function loginAction()
    {
        $auth = Zend_Auth::getInstance();

        $flashMessenger = $this->_helper->getHelper('FlashMessenger');
        $this->view->errorMessages = $flashMessenger->getMessages();

        if ($auth->hasIdentity()) {
            $config = Zend_Registry::get('appConfig');
            $this->_redirect($this->_helper->url->simple(
                $config->auth->login->welcome->action, 
                $config->auth->login->welcome->controller));
        } else if ('' != $this->_request->getParam('openid_mode')) {
            return $this->_forward('verify');
        }

        require_once APPLICATION_PATH . '/forms/LoginOpenIdForm.php';
        $form = new LoginOpenIdForm();
        $form->setAction($this->_helper->url->simple('login'));
        $this->view->form = $form;

        if ($this->_request->isPost()) {
            $formData = $this->_request->getPost();
            if ($form->isValid($formData)) {
                $openIdIdentifier = $this->_request->getPost('openid_identifier');
                $openIdAdapter = new Zend_Auth_Adapter_OpenId($openIdIdentifier);
                $auth->authenticate($openIdAdapter); // will redirect to the open id provider
            } else {
                $form->populate($formData);
            }
        }
    }

    /**
     * verify login action
     *
     */
    public function verifyAction()
    {
        $auth = Zend_Auth::getInstance();
        if ('' != $this->_request->getParam('openid_mode')) {
            $openIdAdapter = new Zend_Auth_Adapter_OpenId();
            $result = $auth->authenticate($openIdAdapter);
            $config = Zend_Registry::get('appConfig');

            $flashMessenger = $this->_helper->getHelper('FlashMessenger');

            if ($result->isValid() && in_array($result->getIdentity(), 
                    $config->auth->identities->toArray())) {
                $auth->getStorage()->write($result->getIdentity());

                $flashMessenger->addMessage('Welcome');
                $this->_redirect($this->_helper->url->simple(
                $config->auth->login->welcome->action, 
                $config->auth->login->welcome->controller));
            } else {
                $auth->clearIdentity();
                foreach ($result->getMessages() as $errorMessage) {
                    $flashMessenger->addMessage($errorMessage);
                }
                $this->_redirect($this->_helper->url->simple('login'));
            }
        }
    }
}
