const { b_message_templates } = require('./blueprint.js');
const { sequelize } = require('../config/database.js');

const table = sequelize.define(...b_message_templates());

class MessageTemplateDatabase {
    constructor() {
        this.table = table;
    }
}

module.exports = MessageTemplateDatabase;
