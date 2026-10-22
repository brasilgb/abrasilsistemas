/** Rótulos compartilhados das telas de SEO (ABRASIL-SEO-02). */
export type AuditStatus = 'queued' | 'running' | 'completed' | 'failed';

export const auditStatusLabels: Record<AuditStatus, string> = {
    queued: 'Na fila',
    running: 'Em execução',
    completed: 'Concluída',
    failed: 'Falhou',
};
