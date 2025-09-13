const { b_workflows } = require('./blueprint.js');
const { sequelize } = require('../config/database.js');

const table = sequelize.define(...b_workflows());

class workflowsDatabase {
    constructor() {
        this.table = table;
    }
}

module.exports = workflowsDatabase;
