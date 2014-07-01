<?php

namespace Cekurte\ZCPEBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Cekurte\ComponentBundle\Util\ContainerAware;
use Cekurte\ZCPEBundle\Event\QuestionAnswerEvent;
use Cekurte\ZCPEBundle\Events;
use Cekurte\ZCPEBundle\Entity\Question;
use Cekurte\ZCPEBundle\Mailer\MessageRFC822;

/**
 * QuestionAnswerListener
 *
 * @author João Paulo Cercal <sistemas@cekurte>
 * @version 1.0
 */
class QuestionAnswerListener extends ContainerAware implements EventSubscriberInterface
{
    const CLRF = "\r\n";

    /**
     * Constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * {@inherited}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::NEW_QUESTION => array(
                array('onCreateNewQuestion', 100),
            ),
        );
    }

    public function getTemplateBody(Question $question)
    {
        $filename = 'CekurteZCPEBundle::email.html.twig';

        return $this->getContainer()->get('templating')->render($filename, array(
            'title'     => 'Cekurte ZCPE',
            'footer'    => '@CekurteZCPE 2014',
            'subject'   => $this->getSubject($question),
            'question'  => $question,
        ));
    }

    /**
     * Get subject
     *
     * @return string
     */
    protected function getSubject(Question $question)
    {
        $container = $this->getContainer();

        return sprintf(
            '[%s] %s: %s',
            $container->getParameter('cekurte_zcpe_google_group_name'),
            $container->getParameter('cekurte_zcpe_google_group_subject'),
            $question->getGoogleGroupsId()
        );
    }

    /**
     * Takes an unencoded string and produces a Base64 encoded string from it.
     *
     * Base64 encoded strings have a maximum line length of 76 characters.
     * If the first line needs to be shorter, indicate the difference with
     * $firstLineOffset.
     *
     * @param string  $string          to encode
     * @param int     $firstLineOffset
     * @param int     $maxLineLength   optional, 0 indicates the default of 76 bytes
     *
     * @return string
     */
    public function encodeString($string, $firstLineOffset = 0, $maxLineLength = 0)
    {
        if (0 >= $maxLineLength || 76 < $maxLineLength) {
            $maxLineLength = 76;
        }

        $encodedString = base64_encode($string);
        $firstLine = '';

        if (0 != $firstLineOffset) {
            $firstLine = substr(
                $encodedString, 0, $maxLineLength - $firstLineOffset
                ) . "\r\n";
            $encodedString = substr(
                $encodedString, $maxLineLength - $firstLineOffset
                );
        }

        return $firstLine . trim(chunk_split($encodedString, $maxLineLength, "\r\n"));
    }

    /**
     * onCreateNewQuestion
     *
     * @param QuestionAnswerEvent $event
     *
     * @return void
     */
    public function onCreateNewQuestion(QuestionAnswerEvent $event)
    {
        $container = $this->getContainer();

        $question = $event->getQuestion();

        $token = $container->get('security.context')->getToken();

        $service = $container->get('cekurte_google_api.gmail');

        if ($service->getClient()->isAccessTokenExpired()) {
            $service->getClient()->refreshToken(
                $token->getRefreshToken()
            );
        }

        // try {

            $messageRFC822 = new MessageRFC822();

            $messageRFC822
                ->addHeader(sprintf(
                    'To: %s',
                    $container->getParameter('cekurte_zcpe_google_group_mail')
                ))
                ->addHeader(sprintf(
                    'From: %s',
                    $this->getUser()->getEmail()
                ))
                ->addHeader(sprintf(
                    'Subject: %s',
                    $this->getSubject($question)
                ))
                ->addHeader('MIME-Version: 1.0')


                ->addHeader('MIME-Version: 1.0')

                ->setMessageHtml(
                    'My message <b>html</b> this.. My message <b>html</b> this.. My message <b>html</b> this.. My message <b>html</b> this.. My message <b>html</b> this..'
                )
            ;

            $messageRawData = $messageRFC822->getRawData();



            $message = new \Google_Service_Gmail_Message();





            $message->setRaw(base64_encode($messageRawData));

            $service->users_messages->send('me', $message);

            $container->get('session')->getFlashBag()->add('message', array(
                'type'      => 'success',
                'message'   => $container->get('translator')->trans('The email has been sent successfully.'),
            ));

        // } catch (\Google_Service_Exception $e) {

        //     var_dump($e);exit;
        //     // $container->get('session')->getFlashBag()->add('message', array(
        //     //     'type'      => 'error',
        //     //     'message'   => $container->get('translator')->trans('The email has been sent successfully.'),
        //     // ));
        // }




        /*

        $message = \Swift_Message::newInstance()
            ->setSubject($this->getSubject($question))
            ->setFrom(array(
                $this->getUser()->getEmail() => $this->getUser()->getName()
            ))
            ->setTo(array(
                $container->getParameter('cekurte_zcpe_google_group_mail') => $container->getParameter('cekurte_zcpe_google_group_name')
            ))
            ->setContentType('text/html')
            ->setBody($this->getTemplateBody($question))
        ;

        $success = $container->get('mailer')->send($message, $failures);

        if ($success == 1) {
            $container->get('session')->getFlashBag()->add('message', array(
                'type'      => 'success',
                'message'   => $container->get('translator')->trans('The email has been sent successfully.'),
            ));
        }
        */

        return;
    }
}
