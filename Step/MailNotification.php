<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Step;

use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Model\Warning;
use Akeneo\Tool\Component\Batch\Step\AbstractStep;
use Akeneo\Platform\Bundle\NotificationBundle\Email\MailNotifier;
use Akeneo\UserManagement\Component\Model\User;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Akeneo\Platform\Bundle\UIBundle\Translator\TranslatorDecorator;
use Twig\Environment;

/**
 * Send email notification after import
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Step
 */
class MailNotification extends AbstractStep
{
    /**
     * Email recipients job parameter
     *
     * @var string
     */
    const JOB_PARAMETERS_EMAIL_RECIPIENTS = 'emailRecipients';

    /**
     * Sucess notification parameter
     *
     * @var string
     */
    const JOB_PARAMETERS_SUCCESS_NOTIFICATION = 'successNotification';

    /**
     * Import step name
     *
     * @var string
     */
    const STEP_NAME_IMPORT = 'import';

    /**
     * Recipients separator
     *
     * @var string
     */
    const RECIPIENTS_SEPARATOR = ',';

    /**
     * Message type reason key
     *
     * @var string
     */
    const MESSAGE_TYPE_REASON_KEY = 'messageType';

    /**
     * Default message type
     *
     * @var string
     */
    const MESSAGE_TYPE_DEFAULT = 'default';

    /**
     * Statistics message type
     *
     * @var string
     */
    const MESSAGE_TYPE_STATISTICS = 'statistics';

    /**
     * @var MailNotifier $notifier
     */
    protected $notifier;

    /**
     * @var Environment $templating
     */
    protected $templating;

    /**
     * @var TranslatorDecorator $translator
     */
    protected $translator;

    /**
     * @var StepExecution $stepExecution
     */
    protected $stepExecution;

    /**
     * Identifier keys for item
     *
     * @var string[]
     */
    protected $identifierKeys = [
        'identifier',
        'code',
    ];

    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param JobRepositoryInterface   $jobRepository
     * @param MailNotifier             $notifier
     * @param Environment              $templating
     * @param TranslatorDecorator      $translator
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        MailNotifier $notifier,
        Environment $templating,
        TranslatorDecorator $translator
    )
    {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->notifier   = $notifier;
        $this->templating = $templating;
        $this->translator = $translator;
    }

    /**
     * @param StepExecution $stepExecution
     */
    protected function doExecute(StepExecution $stepExecution)
    {
        // Check if we have recipients
        $this->stepExecution = $stepExecution;
        $jobParameters       = $this->stepExecution->getJobParameters();
        if (
            !$jobParameters->has(self::JOB_PARAMETERS_EMAIL_RECIPIENTS)
            || empty($jobParameters->get(self::JOB_PARAMETERS_EMAIL_RECIPIENTS))
        ) {
            return;
        }

        // Check if we want success notification
        $successNotification = $jobParameters->has(self::JOB_PARAMETERS_SUCCESS_NOTIFICATION) && $jobParameters->get(self::JOB_PARAMETERS_SUCCESS_NOTIFICATION) === true;

        // Generate and send notifications
        $jobExecution = $this->stepExecution->getJobExecution();
        $recipients   = $jobParameters->get(self::JOB_PARAMETERS_EMAIL_RECIPIENTS);
        $recipients   = explode(self::RECIPIENTS_SEPARATOR, $recipients);
        $mailLabel    = $this->translator->trans('batch_jobs.mail_notification.subject', [
            '%importLabel%' => $jobExecution->getLabel(),
            '%date%'        => $jobExecution->getStartTime()->format('d/m/Y, H:i'),
        ]);

        // Parse data from steps
        $messagesByStepAndType = [];
        /** @var StepExecution $stepExecution */
        foreach ($jobExecution->getStepExecutions() as $stepExecution) {
            // Get statistics data
            $statisticsMessages = $this->getStatisticsByStepExecution($stepExecution);

            // Get warnings
            $warningMessages = [];
            $warnings        = $stepExecution->getWarnings();
            if (!$warnings->isEmpty()) {
                $warningMessages = $this->getMessagesByType($warnings);
            }

            // Skip if notification success is not activated
            if (!$successNotification && empty($warningMessages)) {
                continue;
            }

            $messagesForCurrentStep = array_merge($statisticsMessages, $warningMessages);
            if (!empty($messagesForCurrentStep)) {
                $messagesByStepAndType[$stepExecution->getStepName()] = $messagesForCurrentStep;
            }
        }

        // Send notification
        if (!empty($messagesByStepAndType)) {
            $contentAsHtml = $this->templating->render(
                '@ClickAndMortarAdvancedCsvConnector/notification.html.twig',
                [
                    'messagesByStepAndType' => $messagesByStepAndType,
                ]
            );
            $this->notifier->notify($recipients, $mailLabel, '', $contentAsHtml);
        }
    }

    /**
     * Get statistics used by notification template from $stepExecution
     *
     * @param StepExecution $stepExecution
     *
     * @return array
     */
    protected function getStatisticsByStepExecution($stepExecution)
    {
        $statistics   = [];
        $trackingData = $stepExecution->getTrackingData();
        $readCount    = intval($trackingData['totalItems']);
        if ($readCount === 0) {
            return $statistics;
        }

        $warningCount = count($stepExecution->getWarnings());
        $writeCount   = $readCount - $warningCount;

        // Read lines
        $statistics[] = [
            'content' => $this->translator->trans(
                'batch_jobs.mail_notification.statistics.read',
                [
                    '%readCount%' => $readCount,
                ]
            ),
        ];

        // Write lines
        $statistics[] = [
            'content' => $this->translator->trans(
                'batch_jobs.mail_notification.statistics.write',
                [
                    '%writeCount%' => $writeCount,
                ]
            ),
        ];

        // Warning lines
        $statistics[] = [
            'content' => $this->translator->trans(
                'batch_jobs.mail_notification.statistics.warning',
                [
                    '%warningCount%' => $warningCount,
                ]
            ),
        ];


        return [
            self::MESSAGE_TYPE_STATISTICS => $statistics
        ];
    }

    /**
     * Get messages by type from import step execution $warnings
     *
     * @param Warning[] $warnings
     *
     * @return array
     */
    protected function getMessagesByType($warnings)
    {
        $messages = [];
        foreach ($warnings as $warning) {
            // Get product identifier
            $identifier = null;
            $item       = $warning->getItem();
            foreach ($this->identifierKeys as $identifierKey) {
                if (isset($item[$identifierKey]) && !empty($item[$identifierKey])) {
                    $identifier = $item[$identifierKey];
                    break;
                }
            }

            // Get message type from reason parameters
            $reasonParameters = $warning->getReasonParameters();
            if (
                isset($reasonParameters[self::MESSAGE_TYPE_REASON_KEY])
                && !empty($reasonParameters[self::MESSAGE_TYPE_REASON_KEY])
            ) {
                $messageType = $reasonParameters[self::MESSAGE_TYPE_REASON_KEY];
            } else {
                $messageType = self::MESSAGE_TYPE_DEFAULT;
            }

            // Set message
            if (!array_key_exists($messageType, $messages)) {
                $messages[$messageType] = [];
            }
            $messages[$messageType][] = [
                'identifier' => $identifier,
                'content'    => $this->translator->trans($warning->getReason(), $warning->getReasonParameters()),
            ];
        }

        return $messages;
    }
}
