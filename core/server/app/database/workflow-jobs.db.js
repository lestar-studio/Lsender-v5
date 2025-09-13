const { b_workflow_jobs } = require('./blueprint.js');
const { sequelize } = require('../config/database.js');

const table = sequelize.define(...b_workflow_jobs());

class WorkflowJobsDatabase {
    constructor() {
        this.table = table;
    }
}

module.exports = WorkflowJobsDatabase;
