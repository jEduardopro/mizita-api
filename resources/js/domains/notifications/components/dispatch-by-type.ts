import type { NotificationHandlers, NotificationOfType, NotificationType } from '../types';

export function dispatchByType<Type extends NotificationType, Context, Result>(
    notification: NotificationOfType<Type>,
    handlers: NotificationHandlers<Context, Result>,
    context: Context,
): Result {
    const handler: (notification: NotificationOfType<Type>, context: Context) => Result = handlers[notification.type];

    return handler(notification, context);
}
