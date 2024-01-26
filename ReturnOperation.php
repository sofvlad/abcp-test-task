<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;

/**
 * Назначение кода: Отправка Email и/или SMS работникам, продавцам и клиенту
 * Качество: Трудночитаемый код. Присутствуют непонятные моменты в коде, а также неиспользуемый код. Использование статичных методов. Нагромождение метода doOperation().
 * Итог: Выведены части логики в отдельные методы, помечен и закомментирован неиспользуемый код, рефакторинг.
 */
class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): OperationResultInterface
    {
        $data = (array)$this->getRequest('data');
        $resellerId = (int)$data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = new OperationResult();

        if (empty($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty reseller id';

            return $result;
        }

        if (empty($notificationType)) {
            throw new Exception('Empty notification type', 400);
        }

        // Неиспользуемый код
        // $reseller = $this->contractorLoader->getById($resellerId, Seller::TYPE);
        // if ($reseller === null) {
        //     throw new Exception('Seller not found!', 400);
        // }

        $client = $this->contractorLoader->getById((int)$data['clientId'], Customer::TYPE);
        // $client->Seller->id ?
        if ($client->Seller->id !== $resellerId) {
            throw new \Exception('Client not found!', 400);
        }

        $templateData = $this->prepareTemplate($data, $client, $notificationType, $resellerId);

        $emailFrom = $this->helper->getResellerEmailFrom($resellerId);
        if (!empty($emailFrom)) {
            $result['notificationEmployeeByEmail'] = $this->sendMessagesToEmployees($resellerId, $emailFrom, $templateData);
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType !== self::TYPE_CHANGE || empty($data['differences']['to'])) {
            return $result;
        }

        if (!empty($client->email)) {
            // Не раскрыто назначение MessageTypes и цель использования многих параметров метода sendMessage
            $this->messagesClient->sendMessage(
                [
                    0 => [ // MessageTypes::EMAIL
                       'emailFrom' => $emailFrom,
                       'emailTo'   => $client->email,
                       'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                       'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], 
                $resellerId,
                $client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                (int)$data['differences']['to']
            );
            $result['notificationClientByEmail'] = true;
        }

        if (!empty($client->mobile)) {
            $notificationResult = $this->notificationManager->send(
                $resellerId,
                $client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                (int)$data['differences']['to'],
                $templateData
            );
            if ($notificationResult) {
                $result['notificationClientBySms']['isSent'] = true;
            }
            if (!empty($notificationResult['error'])) {
                $result['notificationClientBySms']['message'] = $notificationResult['error'];
            }
        }

        return $result;
    }

    /**
     * Validate for the fullness of the template data
     * 
     * @param array $templateData
     * 
     * @return void
     * 
     * @throws Exception
     */
    protected function validateTemplateData(array $templateData): void
    {
        foreach ($templateData as $key => $value) {
            if (empty($value) || (is_string($value) && empty(trim($value)))) {
                throw new Exception(sprintf('Template Data "%s" is empty!', $key), 400);
            }
        }
    }

    /**
     * Send messages to employees by reseller id
     * 
     * @param int $resellerId
     * @param string $emailFrom
     * @param array $templateData
     * 
     * @return bool
     */
    protected function sendMessagesToEmployees(int $resellerId, string $emailFrom, array $templateData): bool
    {
        $notificationEmployeeByEmail = false;
        foreach ($this->helper->getEmailsByPermit($resellerId, 'tsGoodsReturn') as $email) {
            $success = $this->messagesClient->sendMessage(
                [
                    0 => [ // MessageTypes::EMAIL
                       'emailFrom' => $emailFrom,
                       'emailTo'   => $email,
                       'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                       'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ],
                $resellerId,
                NotificationEvents::CHANGE_RETURN_STATUS
            );
            if (!empty($success)) {
                $notificationEmployeeByEmail = true;
            }
        }

        return $notificationEmployeeByEmail;
    }

    /**
     * Prepare template data
     * 
     * @param array $data
     * @param Customer $client
     */
    protected function prepareTemplate(array $data, Customer $client, int $notificationType, int $resellerId)
    {
        // Непонятна реализация через магические методы __() и цель использования
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::STATUS_MAP[(int)$data['differences']['from']],
                'TO'   => Status::STATUS_MAP[(int)$data['differences']['to']],
            ], $resellerId);
        }

        $creator = $this->contractorLoader->getById((int)$data['creatorId'], Employee::TYPE);
        $expert = $this->contractorLoader->getById((int)$data['expertId'], Employee::TYPE);

        // @TODO Передалать в объект как OperationResult
        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $creator->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $expert->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $client->getFullName(),
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences ?? '',
        ];

        $this->validateTemplateData($templateData);

        return $data;
    }
}
