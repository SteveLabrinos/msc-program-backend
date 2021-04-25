<?xml version="1.0"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
    <html>
    <body>
        <h2>Student list</h2>

        <table border="1">
            <tr bgcolor="#9b1335">
                <th>Επώνυμο</th>
                <th>Όνομα</th>
                <th>Αριθμός Επιτυχών Μαθημάτων</th>
                <th>Μέσος Όρος Μαθημάτων</th>
            </tr>

            <xsl:for-each select="Students/Student">
                <tr>
                    <td><xsl:value-of select="LastName"/></td>
                    <td><xsl:value-of select="FirstName"/></td>
                    <td><xsl:value-of select="CoursesPasses"/></td>
                    <td><xsl:value-of select="AverageGrade"/></td>
                </tr>
            </xsl:for-each>
        </table>
    </body>
    </html>
</xsl:template>
</xsl:stylesheet>