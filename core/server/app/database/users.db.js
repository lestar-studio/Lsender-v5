const { b_users } = require('./blueprint.js');
const { sequelize } = require('../config/database.js');
const { logger } = require('../lib/myf.velixs.js');

const table = sequelize.define(...b_users());

class UsersDatabase {
    constructor() {
        this.table = table;
    }

    async isAdmin(id) {
        await this.table.findOne({ where: { id: id } }).then((result) => {
            if (result) {
                if (result.dataValues.role == 'admin') {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }).catch((err) => {
            return false;
        });
    }

    async getExpired(id) {
        let result = await this.table.findOne({ where: { id: id } })
        if (result) {
            if(result.role == 'admin') {
                return false;
            }

            if(result.expired_date < this.dateNow()) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    dateNow() {
        let date = new Date();
        let year = date.getFullYear();
        let month = ('0' + (date.getMonth() + 1)).slice(-2);
        let day = ('0' + date.getDate()).slice(-2);
        let formattedDate = `${year}-${month}-${day}`;

        return formattedDate;
    }

}

module.exports = UsersDatabase;
