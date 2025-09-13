const { Op } = require("sequelize");
const WorkflowJobsDatabase = require("../../app/database/workflow-jobs.db");
const SessionConnection = require("../session");
const Client = require("./Client");

class Workflow {
    constructor(socket) {
        this.socket;
    }

    async main() {
        setInterval(() => {
            const jobs = new WorkflowJobsDatabase();
            jobs.table.findAll({
                where: {
                    [Op.or]: [
                        { status: 'pending' },
                        { status: 'failed' }
                    ],
                    attempts: {
                        [Op.lt]: 5
                    }
                }
            }).then(async (result) => {
                result.forEach(async (job) => {
                    let session = await new SessionConnection(this.socket).getSession(job.session_id);
                    if(!session) return;
                    if(session.isStop) return;
                    let client = new Client(session, job.receiver);
                    await client.sendText(job.message).then(async () => {
                        // await jobs.table.update({ status: 'sent' }, { where: { id: job.id } });
                        await jobs.table.destroy({ where: { id: job.id } });
                    }).catch(async () => {
                        await jobs.table.update({ status: 'failed', attempts: job.attempts + 1 }, { where: { id: job.id } });
                    });
                })
            });
        }, 30000); // 30 seconds
    }
}

module.exports = Workflow;
